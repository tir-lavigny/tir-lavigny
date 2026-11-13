<?php

namespace App\Http\Requests\BackOffice;

use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ForceDeleteMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage members') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'confirmation' => ['required', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $member = $this->route('member');

            if (! $member instanceof Member) {
                return;
            }

            $expected = trim($member->first_name.' '.$member->last_name);

            if ($this->input('confirmation') !== $expected) {
                $validator->errors()->add('confirmation', 'La confirmation ne correspond pas au nom du membre.');
            }
        });
    }
}
