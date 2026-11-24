<?php

namespace Tests;

use App\Domains\Invoicing\Models\InvoicingSettings;
use App\Domains\Invoicing\Services\FinancialDocumentGenerator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Support\FakeFinancialDocumentGenerator;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(FinancialDocumentGenerator::class, new FakeFinancialDocumentGenerator);
        Storage::fake('local');

        if (Schema::hasTable('invoicing_settings') && InvoicingSettings::query()->doesntExist()) {
            InvoicingSettings::factory()->create();
        }
    }

    protected function toUtc(?string $localDatetime): ?string
    {
        if (! $localDatetime) {
            return null;
        }

        return Carbon::parse($localDatetime, 'Europe/Zurich')->utc()->toDateTimeString();
    }
}
