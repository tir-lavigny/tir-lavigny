<?php

namespace App\Domains\Invoicing\Services;

use App\Domains\Invoicing\Enums\DeliveryStatus;
use App\Domains\Invoicing\Enums\InvoiceDeliveryMethod;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Database\Eloquent\Builder;

class InvoiceDeliveryQueue
{
    /**
     * @return Builder<Invoice>
     */
    public function pending(InvoiceDeliveryMethod $channel): Builder
    {
        $terminalStatus = $channel === InvoiceDeliveryMethod::Email
            ? DeliveryStatus::Sent
            : DeliveryStatus::Printed;

        return Invoice::query()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
            ])
            ->where('delivery_method', $channel)
            ->whereDoesntHave('deliveries', function (Builder $query) use ($channel, $terminalStatus): void {
                $query->where('channel', $channel)->where('status', $terminalStatus);
            });
    }
}
