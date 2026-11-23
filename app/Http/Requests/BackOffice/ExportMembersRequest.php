<?php

namespace App\Http\Requests\BackOffice;

use App\MemberStatus;
use App\Services\MemberExportService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportMembersRequest extends FormRequest
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
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', Rule::in(MemberStatus::values())],
            'archived' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['name', 'status'])],
            'columns' => ['nullable', 'array'],
            'columns.*' => ['string', Rule::in(array_keys(MemberExportService::COLUMNS))],
            'extra_columns' => ['nullable', 'array', 'max:20'],
            'extra_columns.*.header' => ['required', 'string', 'max:100'],
            'extra_columns.*.width' => ['nullable', 'numeric', 'min:1', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'format.in' => 'Le format doit être CSV ou Excel.',
            'status.in' => 'Le statut demandé est invalide.',
            'sort.in' => 'Le tri demandé est invalide.',
            'columns.*.in' => 'Une des colonnes demandées ne peut pas être exportée.',
            'extra_columns.max' => 'Vous ne pouvez pas ajouter plus de 20 colonnes.',
            'extra_columns.*.header.required' => 'Chaque colonne supplémentaire doit avoir un en-tête.',
        ];
    }
}
