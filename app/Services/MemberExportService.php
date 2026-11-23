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
     * Bounds for a best-fit column width, in Excel character units.
     */
    public const MIN_WIDTH = 8.0;

    public const MAX_WIDTH = 60.0;

    public const WIDTH_PADDING = 2.0;

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
     * Best-fit widths keyed by 1-based Excel column index, the way Excel's
     * "autofit column width" behaves: the widest rendered value or the header,
     * plus a little padding, clamped so a single long value cannot open a
     * 255-character column. A width set on an extra column still wins.
     *
     * @param  Builder<Member>  $query
     * @param  array<int, string>  $columns
     * @param  array<int, array{header: string, width?: float|int|string|null}>  $extraColumns
     * @return array<int, float>
     */
    public function columnWidths(Builder $query, array $columns, array $extraColumns): array
    {
        $lengths = [];

        foreach ($columns as $column) {
            $lengths[] = mb_strlen(self::COLUMNS[$column]);
        }

        foreach ($extraColumns as $extraColumn) {
            $lengths[] = mb_strlen($extraColumn['header']);
        }

        // One extra pass over the rows the export streams, so widths follow the
        // real cell contents: status labels, decrypted AVS, formatted values.
        foreach ($query->cursor() as $member) {
            foreach ($columns as $index => $column) {
                $lengths[$index] = max($lengths[$index], mb_strlen($this->value($member, $column) ?? ''));
            }
        }

        $widths = [];

        foreach ($lengths as $index => $length) {
            $widths[$index + 1] = min(self::MAX_WIDTH, max(self::MIN_WIDTH, $length + self::WIDTH_PADDING));
        }

        foreach (array_values($extraColumns) as $index => $extraColumn) {
            $width = $extraColumn['width'] ?? null;

            if ($width === null || $width === '') {
                continue;
            }

            $widths[count($columns) + $index + 1] = (float) $width;
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
