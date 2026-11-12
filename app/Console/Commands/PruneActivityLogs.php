<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneActivityLogs extends Command
{
    protected $signature = 'activitylogs:prune';

    protected $description = 'Delete activity logs older than the retry window';

    public function handle(): int
    {
        $deleted = ActivityLog::query()
            ->where('created_at', '<', now()->subMonth())
            ->delete();

        Log::info('Activity logs pruned', ['deleted' => $deleted]);

        $this->info("Deleted {$deleted} activity logs.");

        return self::SUCCESS;
    }
}
