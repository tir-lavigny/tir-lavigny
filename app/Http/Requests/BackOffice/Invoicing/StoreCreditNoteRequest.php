<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\CreditNote;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Services\CreditNoteCreditLimit;
use App\Domains\Invoicing\Services\CreditNoteDraftService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', CreditNote::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => ['required', 'integer', Rule::exists('invoices', 'id')],
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
                if ($validator->errors()->hasAny(['items', 'invoice_id'])) {
                    return;
                }

                $invoice = Invoice::query()->find((int) $this->input('invoice_id'));

                if ($invoice === null) {
                    return;
                }

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

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.required' => 'La facture est obligatoire.',
            'reason.required' => "Le motif de l'avoir est obligatoire.",
            'items.required' => "L'avoir doit contenir au moins une ligne.",
            'items.*.unit_price.gt' => 'Le montant doit être positif.',
            'items.*.quantity.gt' => 'La quantité doit être positive.',
        ];
    }
}
