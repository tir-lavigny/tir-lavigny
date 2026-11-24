<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('creditNote')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'max:1000'],
            'issue_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.invoice_item_id' => ['nullable', 'integer', Rule::exists('invoice_items', 'id')],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
            'items.*.metadata' => ['nullable', 'array'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('items')) {
                    return;
                }

                /** @var CreditNote|null $creditNote */
                $creditNote = $this->route('creditNote');

                if (! $creditNote instanceof CreditNote) {
                    return;
                }

                $invoice = Invoice::query()->findOrFail($creditNote->invoice_id);
                $prepared = app(CreditNoteDraftService::class)->prepareItems((array) $this->input('items', []));

                if ($prepared['total_cents'] <= 0) {
                    $validator->errors()->add('items', "Le montant de l'avoir doit être positif.");

                    return;
                }

                $remaining = app(CreditNoteCreditLimit::class)->remainingFor($invoice);

                if ($prepared['total_cents'] > $remaining) {
                    $validator->errors()->add('items', 'Le montant dépasse le montant encore créditable de la facture.');
                }
            },
        ];
    }
}
