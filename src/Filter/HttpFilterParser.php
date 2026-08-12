<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Filter;

use Illuminate\Http\Request;

/**
 * Parses the `?filters[...]` of an HTTP request into a normalized array.
 *
 * Every form is supported:
 *   1. Map: `?filters[email]=ivan&filters[is_active]=1` →
 *      `['email' => 'ivan', 'is_active' => '1']`
 *   2. List: `?filters[][column]=email&filters[][value]=ivan` →
 *      `['email' => 'ivan']`
 *   3. Range/object: `?filters[created_at][from]=...&filters[created_at][to]=...` →
 *      `['created_at' => ['from' => '...', 'to' => '...']]`
 *
 * It also reads `?q=<text>`, the global full-text search. That is NOT a
 * filter, but it comes back under its own key for the controllers' convenience.
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
     * The free text of the global search.
     */
    public static function searchTerm(Request $request): string
    {
        $q = $request->input('q', '');

        return is_string($q) ? trim($q) : '';
    }
}
