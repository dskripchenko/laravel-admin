<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Http\Request;

/**
 * Парсер `?filters[...]` из HTTP-запроса в нормализованный массив.
 *
 * Поддерживает обе формы:
 *   1. Map: `?filters[email]=ivan&filters[is_active]=1` →
 *      `['email' => 'ivan', 'is_active' => '1']`
 *   2. List: `?filters[][column]=email&filters[][value]=ivan` →
 *      `['email' => 'ivan']`
 *   3. Range/object: `?filters[created_at][from]=...&filters[created_at][to]=...` →
 *      `['created_at' => ['from' => '...', 'to' => '...']]`
 *
 * Также читает `?q=<text>` для глобального full-text search'а — это
 * НЕ filter, но возвращается отдельным ключом для удобства controller'ов.
 */
final class HttpFilterParser
{
    /**
     * @return array<string, mixed>
     */
    public static function parse(Request $request): array
    {
        $raw = $request->input('filters', []);
        if (! is_array($raw)) {
            return [];
        }

        $result = [];
        foreach ($raw as $key => $value) {
            if (is_int($key) && is_array($value) && isset($value['column'])) {
                // List form: [{column: 'email', value: 'ivan', operator?: '='}]
                $result[(string) $value['column']] = $value['value'] ?? null;
            } else {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /**
     * Свободный текст для глобального search'а.
     */
    public static function searchTerm(Request $request): string
    {
        $q = $request->input('q', '');

        return is_string($q) ? trim($q) : '';
    }
}
