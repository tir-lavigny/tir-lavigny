<?php

namespace Tests\Feature\Concurrency;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceSequence;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class InvoiceNumberConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUpTraits(): array
    {
        if (! $this->supportsConcurrency()) {
            $this->markTestSkipped('Requires a MariaDB test connection and RUN_CONCURRENCY_TESTS=1.');
        }

        return parent::setUpTraits();
    }

    public function test_parallel_invoice_number_allocation_is_unique(): void
    {
        $script = $this->script(<<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
echo app(App\Domains\Invoicing\Services\InvoiceNumberGenerator::class)->allocate(2027);
PHP);

        $processes = [
            new Process([PHP_BINARY, $script], base_path()),
            new Process([PHP_BINARY, $script], base_path()),
        ];

        try {
            $this->runProcesses($processes);

            $numbers = array_map(fn (Process $process): string => trim($process->getOutput()), $processes);
            sort($numbers);

            $this->assertSame(['2027-0001', '2027-0002'], $numbers);
        } finally {
            InvoiceSequence::query()->where('year', 2027)->delete();
            @unlink($script);
        }
    }

    public function test_parallel_invoice_issuance_allocates_unique_sequential_numbers(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoices = collect([1, 2])->map(fn (): Invoice => app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Cotisation', 'quantity' => '1', 'unit_price' => '150.00'],
            ],
        ], $actor));

        $script = $this->script(<<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Illuminate\Support\Facades\Storage::fake('local');
$app->instance(
    App\Domains\Invoicing\Services\FinancialDocumentGenerator::class,
    new Tests\Support\FakeFinancialDocumentGenerator,
);

try {
    $invoice = App\Domains\Invoicing\Models\Invoice::findOrFail((int) $argv[1]);
    $actor = App\Models\User::findOrFail((int) $argv[2]);
    $issued = app(App\Domains\Invoicing\Actions\Invoices\IssueInvoice::class)->execute($invoice, $actor);
    echo $issued->invoice_number;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage());
    exit(1);
}
PHP);

        $processes = $invoices
            ->map(fn (Invoice $invoice): Process => new Process(
                [PHP_BINARY, $script, (string) $invoice->id, (string) $actor->id],
                base_path(),
            ))
            ->all();

        try {
            $this->runProcesses($processes);

            $numbers = array_map(fn (Process $process): string => trim($process->getOutput()), $processes);
            sort($numbers);

            $this->assertSame(['2027-0001', '2027-0002'], $numbers);
            $this->assertSame(
                ['2027-0001', '2027-0002'],
                Invoice::query()->whereKey($invoices->pluck('id')->all())->orderBy('invoice_number')->pluck('invoice_number')->all(),
            );
        } finally {
            InvoiceSequence::query()->where('year', 2027)->delete();
            @unlink($script);
        }
    }

    /**
     * @param  array<int, Process>  $processes
     */
    private function runProcesses(array $processes): void
    {
        foreach ($processes as $process) {
            $process->start();
        }

        foreach ($processes as $process) {
            $process->wait();

            $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        }
    }

    private function supportsConcurrency(): bool
    {
        $driver = config('database.default');

        return in_array(config("database.connections.{$driver}.driver"), ['mysql', 'mariadb'], true)
            && env('RUN_CONCURRENCY_TESTS') === '1';
    }

    private function script(string $contents): string
    {
        $script = tempnam(sys_get_temp_dir(), 'invoice-number-');

        file_put_contents($script, $contents);

        return $script;
    }
}
