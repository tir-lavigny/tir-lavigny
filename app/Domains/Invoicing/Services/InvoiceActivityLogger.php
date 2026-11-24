<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Models\InvoiceActivityLog;
use App\Models\User;

class InvoiceActivityLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        Invoice $invoice,
        string $action,
        User $actor,
        ?string $description = null,
        array $metadata = [],
    ): InvoiceActivityLog {
        return InvoiceActivityLog::create([
            'invoice_id' => $invoice->id,
            'user_id' => $actor->id,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
    }
}
