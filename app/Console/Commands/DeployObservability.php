<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class DeployObservability extends Command
{
    protected $signature = 'observability:deploy';

    protected $description = 'Arrête proprement et relance le démon Nightwatch';

    public function handle()
    {
        $pidFile = 'observability.pid';

        $this->info('Checking for existing observability process ...');

        $statusCheck = Process::run('php artisan nightwatch:status');

        if ($statusCheck->successful()) {
            $this->info('Existing observability process found.');

            if (Storage::disk('local')->exists($pidFile)) {
                $pid = trim(Storage::disk('local')->get($pidFile));

                if (! empty($pid)) {
                    Process::run("kill -15 {$pid}");
                    sleep(2);
                    Process::run("kill -9 {$pid}");
                }
                Storage::disk('local')->delete($pidFile);
            }
        } else {
            $this->info('No existing observability process found.');
        }

        $this->info('Starting new process ...');

        $process = Process::start('nohup php artisan nightwatch:agent > /dev/null 2>&1 &');
        $pid = $process->id();

        if ($pid) {
            Storage::disk('local')->put($pidFile, $pid);
        }

        sleep(5);

        $finalCheck = Process::run('php artisan nightwatch:status');

        if ($finalCheck->successful()) {
            $this->info('Observability process started successfully.');

            return Command::SUCCESS;
        }
        $this->error('Failed to start observability process.');

        return Command::FAILURE;

    }
}
