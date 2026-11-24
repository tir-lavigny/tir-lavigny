<?php

namespace App\Http\Requests\BackOffice\Invoicing;

use App\Domains\Invoicing\Models\CreditNoteReversal;
use Illuminate\Foundation\Http\FormRequest;

class IssueCreditNoteReversalRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reversal = $this->route('creditNoteReversal');

        return $reversal instanceof CreditNoteReversal
            && ($this->user()?->can('reverse', $reversal->creditNote) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
