<?php

namespace App\Console\Commands;

use App\Models\IdempotencyKey;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:prune';

    protected $description = 'Delete idempotency keys older than the retry window';

    public function handle(): int
    {
        $deleted = IdempotencyKey::query()
            ->where('created_at', '<', now()->subDay())
            ->delete();

        Log::info('Idempotency keys pruned', ['deleted' => $deleted]);

        $this->info("Deleted {$deleted} idempotency keys.");

        return self::SUCCESS;
    }
}
