<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Users: drop unique index on email, widen PII columns, add blind-index hashes
        if (! Schema::hasColumn('users', 'email_hash')) {
            if (Schema::hasIndex('users', 'users_email_unique')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->dropUnique('users_email_unique');
                });
            }
            Schema::table('users', function (Blueprint $table): void {
                $table->text('name')->change();
                $table->text('email')->change();
                $table->text('phone')->nullable()->change();
                $table->char('email_hash', 64)->nullable()->unique()->after('email');
                $table->char('phone_hash', 64)->nullable()->index()->after('phone');
            });
        }

        // 2. Organizations: drop unique on primary_contact_email, widen, add blind-index hash
        if (! Schema::hasColumn('organizations', 'primary_contact_email_hash')) {
            if (Schema::hasIndex('organizations', 'organizations_primary_contact_email_unique')) {
                Schema::table('organizations', function (Blueprint $table): void {
                    $table->dropUnique('organizations_primary_contact_email_unique');
                });
            }
            Schema::table('organizations', function (Blueprint $table): void {
                $table->text('primary_contact_email')->nullable()->change();
                $table->char('primary_contact_email_hash', 64)->nullable()->unique()->after('primary_contact_email');
            });
        }

        // 3. Organization Invitations: drop unique on token & email index, widen, add email_hash & token_hash
        if (! Schema::hasColumn('organization_invitations', 'email_hash')) {
            Schema::table('organization_invitations', function (Blueprint $table): void {
                if (! Schema::hasIndex('organization_invitations', 'organization_invitations_organization_id_index')) {
                    $table->index('organization_id', 'organization_invitations_organization_id_index');
                }
                if (Schema::hasIndex('organization_invitations', 'organization_invitations_token_unique')) {
                    $table->dropUnique('organization_invitations_token_unique');
                }
                if (Schema::hasIndex('organization_invitations', 'idx_oi_org_email')) {
                    $table->dropIndex('idx_oi_org_email');
                }
            });
            Schema::table('organization_invitations', function (Blueprint $table): void {
                $table->text('email')->change();
                $table->text('token')->nullable()->change();
                $table->char('email_hash', 64)->nullable()->index()->after('email');
                $table->char('token_hash', 64)->nullable()->unique()->after('token');
            });
        }

        // 4. Leads: drop plaintext phone index, widen PII & custom_fields, add email_hash & phone_hash
        if (! Schema::hasColumn('leads', 'email_hash')) {
            if (Schema::hasIndex('leads', 'idx_lead_org_phone')) {
                Schema::table('leads', function (Blueprint $table): void {
                    $table->dropIndex('idx_lead_org_phone');
                });
            }
            Schema::table('leads', function (Blueprint $table): void {
                $table->text('first_name')->change();
                $table->text('last_name')->nullable()->change();
                $table->text('email')->nullable()->change();
                $table->text('phone')->nullable()->change();
                $table->text('company_name')->nullable()->change();
                $table->text('location')->nullable()->change();
                $table->longText('requirement')->nullable()->change();
                $table->longText('custom_fields')->nullable()->change();

                $table->char('email_hash', 64)->nullable()->after('email');
                $table->char('phone_hash', 64)->nullable()->after('phone');
                $table->index(['organization_id', 'email_hash'], 'idx_lead_org_email_hash');
                $table->index(['organization_id', 'phone_hash'], 'idx_lead_org_phone_hash');
            });
        }

        // 5. Contacts: drop plaintext indexes, widen PII & custom_fields, add hash indexes
        if (! Schema::hasColumn('contacts', 'email_hash')) {
            Schema::table('contacts', function (Blueprint $table): void {
                if (Schema::hasIndex('contacts', 'idx_contact_org_email')) {
                    $table->dropIndex('idx_contact_org_email');
                }
                if (Schema::hasIndex('contacts', 'idx_contact_org_phone')) {
                    $table->dropIndex('idx_contact_org_phone');
                }
            });
            Schema::table('contacts', function (Blueprint $table): void {
                $table->text('first_name')->change();
                $table->text('last_name')->nullable()->change();
                $table->text('email')->nullable()->change();
                $table->text('phone')->nullable()->change();
                $table->text('job_title')->nullable()->change();
                $table->longText('custom_fields')->nullable()->change();

                $table->char('email_hash', 64)->nullable()->after('email');
                $table->char('phone_hash', 64)->nullable()->after('phone');
                $table->index(['organization_id', 'email_hash'], 'idx_contact_org_email_hash');
                $table->index(['organization_id', 'phone_hash'], 'idx_contact_org_phone_hash');
            });
        }

        // 6. Accounts: drop plaintext indexes, widen name, domain, billing_address, custom_fields
        Schema::table('accounts', function (Blueprint $table): void {
            if (! Schema::hasIndex('accounts', 'accounts_organization_id_index')) {
                $table->index('organization_id', 'accounts_organization_id_index');
            }
            if (Schema::hasIndex('accounts', 'idx_account_org_name')) {
                $table->dropIndex('idx_account_org_name');
            }
            if (Schema::hasIndex('accounts', 'idx_account_org_domain')) {
                $table->dropIndex('idx_account_org_domain');
            }
        });
        Schema::table('accounts', function (Blueprint $table): void {
            $table->text('name')->change();
            $table->text('domain')->nullable()->change();
            $table->longText('billing_address')->nullable()->change();
            $table->longText('custom_fields')->nullable()->change();
        });

        // 7. WhatsApp Accounts: widen display_phone_number, verified_name, webhook_verify_token
        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->text('display_phone_number')->change();
            $table->text('verified_name')->nullable()->change();
            $table->text('webhook_verify_token')->nullable()->change();
        });

        // 8. WhatsApp Conversations: drop plaintext phone index, widen customer_phone, add customer_phone_hash
        if (! Schema::hasColumn('whatsapp_conversations', 'customer_phone_hash')) {
            if (Schema::hasIndex('whatsapp_conversations', 'idx_wc_org_phone')) {
                Schema::table('whatsapp_conversations', function (Blueprint $table): void {
                    $table->dropIndex('idx_wc_org_phone');
                });
            }
            Schema::table('whatsapp_conversations', function (Blueprint $table): void {
                $table->text('customer_phone')->change();
                $table->char('customer_phone_hash', 64)->nullable()->after('customer_phone');
                $table->index(['organization_id', 'customer_phone_hash'], 'idx_wc_org_phone_hash');
            });
        }

        // 9. WhatsApp Messages: widen body & error_payload
        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->longText('body')->nullable()->change();
            $table->longText('error_payload')->nullable()->change();
        });

        // 10. WhatsApp Consents: drop plaintext phone index, widen phone & ip_address, add phone_hash
        if (! Schema::hasColumn('whatsapp_consents', 'phone_hash')) {
            if (Schema::hasIndex('whatsapp_consents', 'idx_wcon_org_phone')) {
                Schema::table('whatsapp_consents', function (Blueprint $table): void {
                    if (! Schema::hasIndex('whatsapp_consents', 'whatsapp_consents_organization_id_index')) {
                        $table->index('organization_id', 'whatsapp_consents_organization_id_index');
                    }
                    $table->dropIndex('idx_wcon_org_phone');
                });
            }
            Schema::table('whatsapp_consents', function (Blueprint $table): void {
                $table->text('phone')->change();
                $table->text('ip_address')->nullable()->change();
                $table->char('phone_hash', 64)->nullable()->after('phone');
                $table->index(['organization_id', 'phone_hash'], 'idx_wcon_org_phone_hash');
            });
        }

        // 11. Payment Gateway Logs: widen JSON payloads & ip_address
        Schema::table('payment_gateway_logs', function (Blueprint $table): void {
            $table->longText('request_headers')->nullable()->change();
            $table->longText('request_payload')->nullable()->change();
            $table->longText('response_payload')->nullable()->change();
            $table->text('ip_address')->nullable()->change();
        });

        // 12. Audit Logs: widen old_values, new_values, ip_address, user_agent
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->longText('old_values')->nullable()->change();
            $table->longText('new_values')->nullable()->change();
            $table->text('ip_address')->nullable()->change();
            $table->text('user_agent')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->json('old_values')->nullable()->change();
            $table->json('new_values')->nullable()->change();
            $table->string('ip_address', 45)->nullable()->change();
            $table->string('user_agent', 255)->nullable()->change();
        });

        Schema::table('payment_gateway_logs', function (Blueprint $table): void {
            $table->json('request_headers')->nullable()->change();
            $table->json('request_payload')->nullable()->change();
            $table->json('response_payload')->nullable()->change();
            $table->string('ip_address', 45)->nullable()->change();
        });

        Schema::table('whatsapp_consents', function (Blueprint $table): void {
            $table->dropIndex('idx_wcon_org_phone_hash');
            $table->dropColumn('phone_hash');
            $table->string('phone', 50)->change();
            $table->string('ip_address', 45)->nullable()->change();
        });

        Schema::table('whatsapp_messages', function (Blueprint $table): void {
            $table->text('body')->nullable()->change();
            $table->json('error_payload')->nullable()->change();
        });

        Schema::table('whatsapp_conversations', function (Blueprint $table): void {
            $table->dropIndex('idx_wc_org_phone_hash');
            $table->dropColumn('customer_phone_hash');
            $table->string('customer_phone', 50)->change();
            $table->index(['organization_id', 'customer_phone'], 'idx_wc_org_phone');
        });

        Schema::table('whatsapp_accounts', function (Blueprint $table): void {
            $table->string('display_phone_number', 50)->change();
            $table->string('verified_name', 191)->nullable()->change();
            $table->string('webhook_verify_token', 100)->nullable()->change();
        });

        Schema::table('accounts', function (Blueprint $table): void {
            $table->string('name', 191)->change();
            $table->string('domain', 191)->nullable()->change();
            $table->json('billing_address')->nullable()->change();
            $table->json('custom_fields')->nullable()->change();
        });

        Schema::table('contacts', function (Blueprint $table): void {
            $table->dropIndex('idx_contact_org_email_hash');
            $table->dropIndex('idx_contact_org_phone_hash');
            $table->dropColumn(['email_hash', 'phone_hash']);
            $table->string('first_name', 100)->change();
            $table->string('last_name', 100)->nullable()->change();
            $table->string('email', 191)->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
            $table->string('job_title', 100)->nullable()->change();
            $table->json('custom_fields')->nullable()->change();
            $table->index(['organization_id', 'email'], 'idx_contact_org_email');
            $table->index(['organization_id', 'phone'], 'idx_contact_org_phone');
        });

        Schema::table('leads', function (Blueprint $table): void {
            $table->dropIndex('idx_lead_org_email_hash');
            $table->dropIndex('idx_lead_org_phone_hash');
            $table->dropColumn(['email_hash', 'phone_hash']);
            $table->string('first_name', 100)->change();
            $table->string('last_name', 100)->nullable()->change();
            $table->string('email', 191)->nullable()->change();
            $table->string('phone', 50)->nullable()->change();
            $table->string('company_name', 191)->nullable()->change();
            $table->string('location', 191)->nullable()->change();
            $table->text('requirement')->nullable()->change();
            $table->json('custom_fields')->nullable()->change();
        });

        Schema::table('organization_invitations', function (Blueprint $table): void {
            $table->dropUnique('organization_invitations_token_hash_unique');
            $table->dropIndex('organization_invitations_email_hash_index');
            $table->dropColumn(['email_hash', 'token_hash']);
            $table->string('email', 191)->change();
            $table->string('token', 64)->unique()->change();
        });

        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropUnique('organizations_primary_contact_email_hash_unique');
            $table->dropColumn('primary_contact_email_hash');
            $table->string('primary_contact_email', 191)->nullable()->unique()->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique('users_email_hash_unique');
            $table->dropIndex('users_phone_hash_index');
            $table->dropColumn(['email_hash', 'phone_hash']);
            $table->string('name')->change();
            $table->string('email')->unique()->change();
            $table->string('phone', 50)->nullable()->change();
        });
    }
};
