<?php

namespace Tests\Feature\Concurrency;

use App\Domains\Invoicing\Models\CreditNoteSequence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class CreditNoteNumberConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_parallel_credit_note_number_allocation_is_unique(): void
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || env('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Requires a MariaDB test connection and RUN_CONCURRENCY_TESTS=1.');
        }

        $script = tempnam(sys_get_temp_dir(), 'credit-note-number-');
        file_put_contents($script, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo app(App\Domains\Invoicing\Services\CreditNoteNumberGenerator::class)->allocate(2027);
PHP);

        $processes = [
            new Process([PHP_BINARY, $script], base_path()),
            new Process([PHP_BINARY, $script], base_path()),
        ];

        try {
            foreach ($processes as $process) {
                $process->start();
            }

            foreach ($processes as $process) {
                $process->wait();

                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }

            $numbers = array_map(fn (Process $process): string => trim($process->getOutput()), $processes);
            sort($numbers);

            $this->assertSame(['CN-2027-0001', 'CN-2027-0002'], $numbers);
        } finally {
            CreditNoteSequence::query()->where('year', 2027)->delete();
            @unlink($script);
        }
    }
}
