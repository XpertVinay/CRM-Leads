<?php

namespace App\Console\Commands;

use App\Support\Security\EncryptedColumnBackfillService;
use Illuminate\Console\Command;

class RotateEncryptionKeysCommand extends Command
{
    protected $signature = 'crm:rotate-encryption-keys {--chunk=200 : Number of records per batch}';

    protected $description = 'Re-encrypt database records from DATA_ENCRYPTION_PREVIOUS_KEYS to the current DATA_ENCRYPTION_KEY.';

    public function handle(EncryptedColumnBackfillService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $totalRotated = $service->process(
            rotateExisting: true,
            chunkSize: $chunkSize,
            onModelComplete: fn (string $model, int $count) => $this->line(" - {$model}: {$count} record(s) rotated"),
        );

        $this->info("Key rotation complete. Total records rotated: {$totalRotated}");

        return self::SUCCESS;
    }
}
