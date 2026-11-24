<?php

namespace Tests\Feature\Concurrency;

use App\Domains\Invoicing\Actions\CreditNotes\CreateCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\IssueCreditNote;
use App\Domains\Invoicing\Actions\CreditNotes\ReverseCreditNoteApplication;
use App\Domains\Invoicing\Actions\Invoices\CreateInvoice;
use App\Domains\Invoicing\Actions\Invoices\IssueInvoice;
use App\Domains\Invoicing\Models\BillingCustomer;
use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\CreditNoteApplication;
use App\Domains\Invoicing\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use PHPUnit\Framework\Attributes\Group;
use Symfony\Component\Process\Process;
use Tests\TestCase;

#[Group('concurrency')]
class CreditNoteApplicationConcurrencyTest extends TestCase
{
    protected function setUpTraits(): array
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || env('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Requires a MariaDB test connection and RUN_CONCURRENCY_TESTS=1.');
        }

        return parent::setUpTraits();
    }

    use DatabaseTruncation;

    public function test_concurrent_credit_applications_cannot_over_allocate(): void
    {
        $driver = config('database.default');
        $connection = config("database.connections.{$driver}.driver");

        if (! in_array($connection, ['mysql', 'mariadb'], true) || env('RUN_CONCURRENCY_TESTS') !== '1') {
            $this->markTestSkipped('Requires a MariaDB test connection and RUN_CONCURRENCY_TESTS=1.');
        }

        $actor = User::factory()->create();
        $customer = BillingCustomer::factory()->create();
        $invoiceA = $this->issuedInvoice($customer, $actor, '100.00');
        $invoiceB = $this->issuedInvoice($customer, $actor, '100.00');
        $creditNote = $this->issuedCreditNote($invoiceA, $actor, '100.00');
        $application = $creditNote->applications()->firstOrFail();
        app(ReverseCreditNoteApplication::class)->execute($application, 10000, 'Recredit', $actor);

        $script = tempnam(sys_get_temp_dir(), 'credit-note-application-');
        file_put_contents($script, <<<'PHP'
<?php
require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

try {
    app(App\Domains\Invoicing\Actions\CreditNotes\ApplyCreditNoteToInvoice::class)->execute(
        App\Domains\Invoicing\Models\CreditNote::findOrFail((int) $argv[1]),
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
            new Process([PHP_BINARY, $script, (string) $creditNote->id, (string) $invoiceB->id, (string) $actor->id], base_path()),
            new Process([PHP_BINARY, $script, (string) $creditNote->id, (string) $invoiceB->id, (string) $actor->id], base_path()),
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
            $this->assertSame(6000, (int) CreditNoteApplication::query()
                ->where('credit_note_id', $creditNote->id)
                ->where('invoice_id', $invoiceB->id)
                ->sum('amount_cents'));
        } finally {
            @unlink($script);
        }
    }

    private function issuedInvoice(BillingCustomer $customer, User $actor, string $amount): Invoice
    {
        $invoice = app(CreateInvoice::class)->execute([
            'billing_customer_id' => $customer->id,
            'delivery_method' => 'email',
            'issue_date' => '2027-01-15',
            'due_date' => '2027-02-14',
            'items' => [
                ['type' => 'fee', 'description' => 'Prestation', 'quantity' => '1', 'unit_price' => $amount],
            ],
        ], $actor);

        return app(IssueInvoice::class)->execute($invoice, $actor);
    }

    private function issuedCreditNote(Invoice $invoice, User $actor, string $amount): CreditNote
    {
        $creditNote = app(CreateCreditNote::class)->execute([
            'invoice_id' => $invoice->id,
            'reason' => 'Correction',
            'issue_date' => '2027-01-20',
            'items' => [['description' => 'Avoir', 'quantity' => '1', 'unit_price' => $amount]],
        ], $actor);

        return app(IssueCreditNote::class)->execute($creditNote, $actor);
    }
}
