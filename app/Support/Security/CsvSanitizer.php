<?php

namespace App\Support\Security;

final class CsvSanitizer
{
    /**
     * Neutralize spreadsheet formula prefixes (=, +, -, @, tab, CR) in a single CSV cell.
     */
    public static function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $stringValue = (string) $value;
        if ($stringValue === '') {
            return '';
        }

        if (preg_match('/^[\t\r\n ]*[=+\-@]/', $stringValue) === 1 || str_starts_with($stringValue, "\t") || str_starts_with($stringValue, "\r")) {
            return "'".$stringValue;
        }

        return $stringValue;
    }

    /**
     * Sanitize an entire CSV row before passing it to fputcsv().
     *
     * @param  array<int, mixed>  $row
     * @return array<int, string>
     */
    public static function sanitizeRow(array $row): array
    {
        return array_map([self::class, 'sanitizeCell'], $row);
    }
}
