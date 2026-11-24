<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\Invoice;
use App\Http\Requests\BackOffice\Invoicing\Concerns\ValidatesInvoiceLines;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewInvoiceRequest extends FormRequest
{
    use ValidatesInvoiceLines;

    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->invoiceLineRules();
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->validateInvoiceTotals($validator);
            },
        ];
    }
}
