<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\CreditNote;
use Illuminate\Foundation\Http\FormRequest;

class PreviewCreditNoteRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
