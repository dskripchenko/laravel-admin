<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Import;

use Dskripchenko\LaravelAdmin\Field\Field;

/**
 * Маппит колонки CSV/XLSX → Field-имена Resource'а.
 *
 * Стратегии auto-маппинга:
 *   1. exact match — `name` ↔ `name`.
 *   2. case-insensitive match — `Name` ↔ `name`.
 *   3. label match — `Имя` ↔ Field title.
 *   4. snake_case match — `Created At` ↔ `created_at`.
 *
 * Возвращает массив `[csv_header => field_name]`. CSV-header'ы без матча
 * не попадают в результат (пропустятся при импорте).
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
     * Применить mapping к row из CSV: [csv_header => value] → [field_name => value].
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
