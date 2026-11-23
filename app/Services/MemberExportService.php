<?php

namespace App\Services;

use App\MemberStatus;
use App\Models\Member;
use App\Traits\SearchesAccentInsensitively;
use Illuminate\Database\Eloquent\Builder;

class MemberExportService
{
    use SearchesAccentInsensitively;

    public const SENSITIVE_PERMISSION = 'view member sensitive data';

    /**
     * Exportable member columns, keyed by attribute and labelled for the file header.
     *
     * @var array<string, string>
     */
    public const COLUMNS = [
        'first_name' => 'Prénom',
        'last_name' => 'Nom',
        'email' => 'Email',
        'tel' => 'Téléphone',
        'street' => 'Rue',
        'npa' => 'NPA',
        'city' => 'Ville',
        'canton' => 'Canton',
        'country' => 'Pays',
        'status' => 'Statut',
        'avs' => 'AVS',
        'is_swiss_citizen' => 'Nationalité suisse',
    ];

    /**
     * Columns that require the "view member sensitive data" permission.
     *
     * @var array<int, string>
     */
    public const SENSITIVE_COLUMNS = ['avs', 'is_swiss_citizen'];

    /**
     * Columns exported when the request does not select any.
     *
     * @var array<int, string>
     */
    public const DEFAULT_COLUMNS = ['last_name', 'first_name', 'email', 'tel', 'status'];

    /**
     * @param  array{search?: string|null, status?: string|null, archived?: bool, sort?: string|null}  $filters
     * @return Builder<Member>
     */
    public function memberQuery(array $filters): Builder
    {
        $query = Member::query();

        if ((bool) ($filters['archived'] ?? false)) {
            $query->onlyTrashed();
        } else {
            $query->withoutTrashed();
        }

        $status = $filters['status'] ?? null;

        if (is_string($status) && in_array($status, MemberStatus::values(), true)) {
            $query->where('status', $status);
        }

        $search = $filters['search'] ?? null;

        $this->whereAccentInsensitiveLike($query, ['first_name', 'last_name', 'email'], is_string($search) ? $search : null);

        if (($filters['sort'] ?? 'name') === 'status') {
            $query->orderBy('status')->orderByName();
        } else {
            $query->orderByName();
        }

        return $query;
    }

    /**
     * Keep the requested columns that exist and that the user may export, in the requested order.
     *
     * @param  array<int, string>  $requested
     * @return array<int, string>
     */
    public function resolveColumns(array $requested, bool $canViewSensitiveData): array
    {
        $columns = array_values(array_unique(array_intersect($requested, array_keys(self::COLUMNS))));

        if (! $canViewSensitiveData) {
            $columns = array_values(array_diff($columns, self::SENSITIVE_COLUMNS));
        }

        return $columns === [] ? self::DEFAULT_COLUMNS : $columns;
    }

    /**
     * @param  array<int, string>  $columns
     * @param  array<int, array{header: string, width?: float|int|null}>  $extraColumns
     * @return array<int, string>
     */
    public function headers(array $columns, array $extraColumns): array
    {
        $headers = array_map(
            static fn (string $column): string => self::COLUMNS[$column],
            $columns,
        );

        foreach ($extraColumns as $extraColumn) {
            $headers[] = $extraColumn['header'];
        }

        return $headers;
    }

    /**
     * @param  Builder<Member>  $query
     * @param  array<int, string>  $columns
     * @return iterable<int, array<int, string|null>>
     */
    public function rows(Builder $query, array $columns, int $extraColumnCount): iterable
    {
        foreach ($query->cursor() as $member) {
            $row = [];

            foreach ($columns as $column) {
                $row[] = $this->value($member, $column);
            }

            for ($index = 0; $index < $extraColumnCount; $index++) {
                $row[] = '';
            }

            yield $row;
        }
    }

    /**
     * Widths for the extra columns, keyed by 1-based Excel column index.
     *
     * @param  array<int, array{header: string, width?: float|int|string|null}>  $extraColumns
     * @return array<int, float>
     */
    public function extraColumnWidths(int $columnCount, array $extraColumns): array
    {
        $widths = [];

        foreach (array_values($extraColumns) as $index => $extraColumn) {
            $width = $extraColumn['width'] ?? null;

            if ($width === null || $width === '') {
                continue;
            }

            $widths[$columnCount + $index + 1] = (float) $width;
        }

        return $widths;
    }

    public function value(Member $member, string $column): ?string
    {
        if ($column === 'status') {
            return $member->status->label();
        }

        if ($column === 'is_swiss_citizen') {
            return match ($member->is_swiss_citizen) {
                true => 'Oui',
                false => 'Non',
                null => null,
            };
        }

        $value = $member->getAttribute($column);

        return is_string($value) || is_numeric($value) ? (string) $value : null;
    }
}
