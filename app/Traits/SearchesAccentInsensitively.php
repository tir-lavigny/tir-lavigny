<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

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

        $driver = $query->getConnection()->getConfig('driver');
        $grammar = $query->getQuery()->getGrammar();
        $term = $driver === 'sqlite' ? Str::lower(Str::ascii($search)) : $search;

        $query->where(function (Builder $query) use ($columns, $driver, $grammar, $term): void {
            foreach ($columns as $column) {
                $wrappedColumn = $grammar->wrap($column);
                $expression = $driver === 'sqlite'
                    ? $query->getConnection()->raw("unaccent({$wrappedColumn})")
                    : $query->getConnection()->raw("{$wrappedColumn} COLLATE utf8mb4_unicode_ci");

                $query->orWhere($expression, 'LIKE', "%{$term}%");
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
        $driver = $query->getConnection()->getConfig('driver');
        $grammar = $query->getQuery()->getGrammar();

        foreach ($columns as $column) {
            $expression = $driver === 'sqlite'
                ? $query->getConnection()->raw('unaccent('.$grammar->wrap($column).')')
                : $column;

            $query->orderBy($expression);
        }
    }
}
