<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Modules\Pipeline\Services\StaleDealSlaService;
use Illuminate\Console\Command;

class DetectStaleDealCommand extends Command
{
    protected $signature = 'crm:detect-stale-deals {--org= : Organization ID to check (checks all if omitted)}';

    protected $description = 'Detect and flag deals that have exceeded their stage SLA threshold';

    public function handle(StaleDealSlaService $slaService): int
    {
        $orgId = $this->option('org');

        if ($orgId !== null) {
            $flagged = $slaService->detectAndFlagStaleDeals((int) $orgId);
            $this->info("Flagged {$flagged} stale deal(s) for organization #{$orgId}.");

            return self::SUCCESS;
        }

        $totalFlagged = 0;
        $organizations = Organization::withoutGlobalScopes()->get();

        foreach ($organizations as $org) {
            $flagged = $slaService->detectAndFlagStaleDeals((int) $org->id);
            if ($flagged > 0) {
                $this->line("  Org #{$org->id} ({$org->name}): {$flagged} stale deal(s).");
            }
            $totalFlagged += $flagged;
        }

        $this->info("Total: flagged {$totalFlagged} stale deal(s) across {$organizations->count()} organization(s).");

        return self::SUCCESS;
    }
}
