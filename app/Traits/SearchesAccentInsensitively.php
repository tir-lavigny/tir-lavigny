<?php

namespace App\Traits;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Str;
use InvalidArgumentException;

trait SearchesAccentInsensitively
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     */
    protected function whereAccentInsensitiveLike(Builder $query, array $columns, ?string $search): void
    {
        $search = trim((string) $search);

        if ($search === '') {
            return;
        }

        $connection = $query->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;
        $term = $driver === 'sqlite' ? Str::lower(Str::ascii($search)) : $search;

        $query->where(function (Builder $query) use ($columns, $driver, $term): void {
            foreach ($columns as $column) {
                $query->orWhere(new Expression($this->accentInsensitiveExpression($column, $driver)), 'LIKE', "%{$term}%");
            }
        });
    }

    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<int, string>  $columns
     */
    protected function orderByAccentInsensitive(Builder $query, array $columns): void
    {
        $connection = $query->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;

        foreach ($columns as $column) {
            $query->orderBy(new Expression($this->accentInsensitiveExpression($column, $driver)));
        }
    }

    /**
     * @return literal-string
     */
    private function accentInsensitiveExpression(string $column, ?string $driver): string
    {
        return match ($column) {
            'first_name' => $driver === 'sqlite' ? 'unaccent(first_name)' : 'first_name COLLATE utf8mb4_unicode_ci',
            'last_name' => $driver === 'sqlite' ? 'unaccent(last_name)' : 'last_name COLLATE utf8mb4_unicode_ci',
            'name' => $driver === 'sqlite' ? 'unaccent(name)' : 'name COLLATE utf8mb4_unicode_ci',
            'title' => $driver === 'sqlite' ? 'unaccent(title)' : 'title COLLATE utf8mb4_unicode_ci',
            'email' => $driver === 'sqlite' ? 'unaccent(email)' : 'email COLLATE utf8mb4_unicode_ci',
            default => throw new InvalidArgumentException("Unsupported accent-insensitive column [{$column}]."),
        };
    }
}
