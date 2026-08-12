<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Field\Field;

/**
 * Maps the columns of a CSV or XLSX onto a resource's field names.
 *
 * The automatic strategies:
 *   1. an exact match — `name` ↔ `name`.
 *   2. a case-insensitive match — `Name` ↔ `name`.
 *   3. a label match — the field's title ↔ the header.
 *   4. a snake_case match — `Created At` ↔ `created_at`.
 *
 * It returns `[csv_header => field_name]`. The headers that match nothing are
 * left out of the result and skipped during the import.
 */
final class ColumnMapper
{
    /**
     * @param  list<string>  $csvHeaders
     * @param  list<Field>  $fields
     * @return array<string, string> csv_header => field_name
     */
    public static function autoMap(array $csvHeaders, array $fields): array
    {
        $byName = [];
        $byNameCi = [];
        $byLabel = [];
        $bySnake = [];
        foreach ($fields as $field) {
            $name = $field->name();
            $byName[$name] = $name;
            $byNameCi[strtolower($name)] = $name;
            $title = (string) ($field->getAttributes()['title'] ?? '');
            if ($title !== '') {
                $byLabel[strtolower(trim($title))] = $name;
            }
            $bySnake[self::snake(strtolower($name))] = $name;
        }

        $mapping = [];
        foreach ($csvHeaders as $header) {
            $h = trim($header);
            if (isset($byName[$h])) {
                $mapping[$header] = $byName[$h];

                continue;
            }
            $hLower = strtolower($h);
            if (isset($byNameCi[$hLower])) {
                $mapping[$header] = $byNameCi[$hLower];

                continue;
            }
            if (isset($byLabel[$hLower])) {
                $mapping[$header] = $byLabel[$hLower];

                continue;
            }
            $hSnake = self::snake($hLower);
            if (isset($bySnake[$hSnake])) {
                $mapping[$header] = $bySnake[$hSnake];

                continue;
            }
        }

        return $mapping;
    }

    /**
     * Applies the mapping to a CSV row: [csv_header => value] → [field_name => value].
     *
     * @param  array<string, mixed>  $row
     * @param  array<string, string>  $mapping
     * @return array<string, mixed>
     */
    public static function applyMapping(array $row, array $mapping): array
    {
        $result = [];
        foreach ($mapping as $header => $field) {
            if (array_key_exists($header, $row)) {
                $result[$field] = $row[$header];
            }
        }

        return $result;
    }

    private static function snake(string $value): string
    {
        $value = preg_replace('/[\s\-]+/', '_', $value) ?? $value;

        return preg_replace('/_+/', '_', strtolower($value)) ?? $value;
    }
}
