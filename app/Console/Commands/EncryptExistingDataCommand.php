<?php

namespace App\Console\Commands;

use App\Support\Security\EncryptedColumnBackfillService;
use Illuminate\Console\Command;

class EncryptExistingDataCommand extends Command
{
    protected $signature = 'crm:encrypt-existing-data {--chunk=200 : Number of records per batch}';

    protected $description = 'Encrypt existing plaintext database columns and populate blind-index hashes idempotently.';

    public function handle(EncryptedColumnBackfillService $service): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));

        $totalUpdated = $service->process(
            rotateExisting: false,
            chunkSize: $chunkSize,
            onModelComplete: fn (string $model, int $count) => $this->line(" - {$model}: {$count} record(s) encrypted/indexed"),
        );

        $this->info("Done. Total records updated: {$totalUpdated}");

        return self::SUCCESS;
    }
}
