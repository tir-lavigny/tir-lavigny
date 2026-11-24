<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCreditNoteReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('creditNote')) ?? false;
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
            'items.*.credit_note_item_id' => ['nullable', 'integer', Rule::exists('credit_note_items', 'id')],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.amount' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
