<?php

namespace Tests\Unit\Models;

use App\Domains\Invoicing\Models\InvoiceActivityLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

class InvoiceActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_log_can_be_created(): void
    {
        $activityLog = InvoiceActivityLog::factory()->create(['action' => 'invoice.created']);

        $this->assertModelExists($activityLog);
        $this->assertSame('invoice.created', $activityLog->action);
    }

    public function test_activity_log_cannot_be_updated(): void
    {
        $activityLog = InvoiceActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invoice activity logs cannot be updated.');

        $activityLog->update(['action' => 'invoice.changed']);
    }

    public function test_activity_log_cannot_be_deleted(): void
    {
        $activityLog = InvoiceActivityLog::factory()->create();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Invoice activity logs cannot be deleted.');

        $activityLog->delete();
    }
}
