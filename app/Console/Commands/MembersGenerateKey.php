<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MembersGenerateKey extends Command
{
    protected $signature = 'members:key
        {--path= : Environment file to update}
        {--force : Overwrite an existing MEMBER_DATA_KEY}';

    protected $description = 'Generate or replace MEMBER_DATA_KEY in the environment file.';

    public function handle(): int
    {
        $path = $this->option('path') ?: base_path('.env');

        if (! is_file($path)) {
            $this->error("Environment file not found: {$path}");

            return self::FAILURE;
        }

        $key = 'base64:'.base64_encode(random_bytes(32));
        $contents = (string) file_get_contents($path);

        if (preg_match('/^MEMBER_DATA_KEY=(.+)$/m', $contents) === 1 && ! $this->option('force')) {
            $this->error('MEMBER_DATA_KEY already exists. Run members:reencrypt-avs before rotating, then use --force.');
            $this->line('Overwriting without re-encryption makes existing AVS values unreadable.');

            return self::FAILURE;
        }

        if (preg_match('/^MEMBER_DATA_KEY=.*$/m', $contents) === 1) {
            $contents = (string) preg_replace('/^MEMBER_DATA_KEY=.*$/m', 'MEMBER_DATA_KEY='.$key, $contents);
        } else {
            $contents = rtrim($contents, "\n")."\nMEMBER_DATA_KEY={$key}\n";
        }

        file_put_contents($path, $contents);

        $this->info('MEMBER_DATA_KEY generated.');

        return self::SUCCESS;
    }
}
