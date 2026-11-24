<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\CreditNoteApplication;
use Illuminate\Foundation\Http\FormRequest;

class ReverseCreditNoteApplicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $application = $this->route('creditNoteApplication');

        return $application instanceof CreditNoteApplication
            && ($this->user()?->can('reverse', $application->creditNote) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
