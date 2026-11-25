<?php

namespace Tests\Feature\Concurrency;

use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Actions\Payments\RegisterPayment;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\InvoicePayment;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class PaymentAllocationConcurrencyTest extends TestCase
{
    use DatabaseTruncation;

    protected function setUpTraits(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || env('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Requires a MariaDB test connection and RUN_CONCURRENCY_TESTS=1.');
        }

        return parent::setUpTraits();
    }

    public function test_concurrent_payment_allocations_cannot_over_allocate(): void
    {
        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoice = app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [['type' => 'fee', 'description' => 'Prestation', 'quantity' => '1', 'unit_price' => '100.00']],
        ], $actor);
        $invoice = app(IssueInvoice::class)->execute($invoice, $actor);

        $payment = app(RegisterPayment::class)->execute([
            'amount_cents' => 10000,
            'method' => PaymentMethod::BankTransfer->value,
            'payment_date' => '2027-02-20',
            'reference' => 'CONCURRENT',
            'notes' => null,
            'idempotency_key' => (string) Str::uuid(),
        ], $actor);

        $script = tempnam(sys_get_temp_dir(), 'payment-allocation-');
        file_put_contents($script, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Domains\Invoicing\Actions\Payments\AllocatePayment::class)->execute(
        App\Domains\Invoicing\Models\Payment::findOrFail((int) $argv[1]),
        App\Domains\Invoicing\Models\Invoice::findOrFail((int) $argv[2]),
        6000,
        App\Models\User::findOrFail((int) $argv[3]),
    );
    echo 'ok';
} catch (Throwable $exception) {
    echo 'error';
}
PHP);

        $processes = [
            new Process([PHP_BINARY, $script, (string) $payment->id, (string) $invoice->id, (string) $actor->id], base_path()),
            new Process([PHP_BINARY, $script, (string) $payment->id, (string) $invoice->id, (string) $actor->id], base_path()),
        ];

        try {
            foreach ($processes as $process) {
                $process->start();
            }

            foreach ($processes as $process) {
                $process->wait();

                $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
            }

            $results = array_map(fn (Process $process): string => trim($process->getOutput()), $processes);
            sort($results);

            $this->assertSame(['error', 'ok'], $results);
            $this->assertSame(6000, (int) InvoicePayment::query()
                ->where('payment_id', $payment->id)
                ->where('invoice_id', $invoice->id)
                ->sum('amount_cents'));
        } finally {
            @unlink($script);
        }
    }
}
