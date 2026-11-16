<?php

namespace App\Http\Requests\BackOffice;

use Illuminate\Foundation\Http\FormRequest;

class ArchiveMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()?->can('manage members')) {
            return false;
        }

        if ($this->boolean('delete_vouchers') && ! $this->user()->can('manage vouchers')) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delete_vouchers' => ['nullable', 'boolean'],
        ];
    }
}
