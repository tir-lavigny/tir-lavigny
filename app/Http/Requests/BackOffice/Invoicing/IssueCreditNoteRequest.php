<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use Illuminate\Foundation\Http\FormRequest;

class IssueCreditNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('issue', $this->route('creditNote')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
