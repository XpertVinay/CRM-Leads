<?php

namespace App\Support\Security;

use App\Models\Organization;
use App\Models\User;
use App\Modules\Communications\WhatsApp\Models\WhatsAppAccount;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConsent;
use App\Modules\Communications\WhatsApp\Models\WhatsAppConversation;
use App\Modules\Communications\WhatsApp\Models\WhatsAppMessage;
use App\Modules\Compliance\Models\AuditLog;
use App\Modules\Leads\Models\Lead;
use App\Modules\Pipeline\Models\Account;
use App\Modules\Pipeline\Models\Contact;
use App\Modules\Subscriptions\Models\PaymentGatewayLog;
use App\Modules\Tenancy\Models\OrganizationInvitation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class EncryptedColumnBackfillService
{
    /**
     * @var array<class-string<Model>, list<string>>
     */
    private const TARGET_MODELS = [
        User::class => ['name', 'email', 'phone'],
        Organization::class => ['primary_contact_email'],
        OrganizationInvitation::class => ['email', 'token'],
        Lead::class => ['first_name', 'last_name', 'email', 'phone', 'company_name', 'location', 'requirement', 'custom_fields'],
        Contact::class => ['first_name', 'last_name', 'email', 'phone', 'job_title', 'custom_fields'],
        Account::class => ['name', 'domain', 'billing_address', 'custom_fields'],
        WhatsAppAccount::class => ['display_phone_number', 'verified_name', 'webhook_verify_token', 'access_token_encrypted'],
        WhatsAppConversation::class => ['customer_phone'],
        WhatsAppMessage::class => ['body', 'error_payload'],
        WhatsAppConsent::class => ['phone', 'ip_address'],
        PaymentGatewayLog::class => ['request_headers', 'request_payload', 'response_payload', 'ip_address'],
        AuditLog::class => ['old_values', 'new_values', 'ip_address', 'user_agent'],
    ];

    public function __construct(private readonly DataEncrypter $encrypter) {}

    /**
     * @param  callable(string, int): void|null  $onModelComplete
     */
    public function process(bool $rotateExisting, int $chunkSize = 200, ?callable $onModelComplete = null): int
    {
        $totalUpdated = 0;

        foreach (self::TARGET_MODELS as $modelClass => $columns) {
            $query = $modelClass::withoutGlobalScopes();
            if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
                $query->withTrashed();
            }

            $modelCount = 0;
            $table = (new $modelClass())->getTable();

            $query->orderBy('id')->chunkById($chunkSize, function ($records) use ($table, $columns, $rotateExisting, &$modelCount): void {
                foreach ($records as $record) {
                    $raw = DB::table($table)->where('id', $record->getKey())->first();
                    if (! $raw) {
                        continue;
                    }

                    if (! $this->recordNeedsUpdate($raw, $columns, $rotateExisting)) {
                        continue;
                    }

                    if ($record instanceof WhatsAppAccount && is_string($raw->access_token_encrypted ?? null) && $raw->access_token_encrypted !== '') {
                        $record->setPlainAccessToken($record->getPlainAccessToken());
                    }

                    foreach ($columns as $col) {
                        if ($col === 'access_token_encrypted') {
                            continue;
                        }
                        $record->setAttribute($col, $record->getAttribute($col));
                    }

                    if (method_exists($record, 'syncBlindIndexes')) {
                        $record->syncBlindIndexes();
                    }

                    $record->saveQuietly();
                    $modelCount++;
                }
            });

            $totalUpdated += $modelCount;
            if ($onModelComplete !== null) {
                $onModelComplete(class_basename($modelClass), $modelCount);
            }
        }

        return $totalUpdated;
    }

    /**
     * @param  list<string>  $columns
     */
    private function recordNeedsUpdate(object $raw, array $columns, bool $rotateExisting): bool
    {
        foreach ($columns as $col) {
            $rawVal = $raw->{$col} ?? null;
            if (! is_string($rawVal) || $rawVal === '') {
                continue;
            }

            if ($rotateExisting ? $this->encrypter->needsReEncryption($rawVal) : ! $this->encrypter->isEncryptedPayload($rawVal)) {
                return true;
            }
        }

        return false;
    }
}
