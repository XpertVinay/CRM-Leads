<?php

namespace App\Support\Queries;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class SqlSearchHelper
{
    /**
     * Escape SQL LIKE wildcard characters (\, %, _).
     */
    public static function escapeLike(string $term): string
    {
        return addcslashes($term, '\\%_');
    }

    /**
     * Build a lowercased, wildcard-escaped LIKE needle (%term%).
     */
    public static function containsNeedle(string $term): string
    {
        return '%'.self::escapeLike(Str::lower(trim($term))).'%';
    }

    /**
     * Apply a case-insensitive LIKE search across one or more columns.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, string>  $columns
     */
    public static function applyCaseInsensitiveLike(Builder $query, array $columns, string $term): Builder
    {
        $trimmed = trim($term);
        if ($trimmed === '' || $columns === []) {
            return $query;
        }

        $needle = self::containsNeedle($trimmed);

        return $query->where(function (Builder $sub) use ($columns, $needle): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'whereRaw' : 'orWhereRaw';
                $sub->{$method}("LOWER(COALESCE({$column}, '')) LIKE ?", [$needle]);
            }
        });
    }
}
