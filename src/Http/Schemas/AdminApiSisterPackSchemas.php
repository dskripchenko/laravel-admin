<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * The templates of the sister packs' actions: laravel-admin-search and
 * laravel-admin-health.
 *
 * They are declared in the core admin for the API documentation's convenience.
 * In a real build the sister packs register their own getOpenApiTemplates()
 * through AdminPlugin — the contract is in ARCHITECTURE.md §5.23. This trait
 * remains for the cases where a sister pack is not installed yet but the
 * OpenAPI document should still show the full contract.
 */
trait AdminApiSisterPackSchemas
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function provideSisterPackSchemas(): array
    {
        return [

            /* ------------------------------------------------------------------
             * Search (laravel-admin-search)
             * ------------------------------------------------------------------ */

            'SearchResponse' => [
                'success' => 'boolean!',
                'payload' => '@SearchPayload',
            ],
            'SearchPayload' => [
                'query' => 'string!',
                'groups' => '@SearchGroup[]',
                'total' => 'integer!',
                'elapsed_ms' => 'integer!',
            ],
            'SearchGroup' => [
                'resource' => 'string!',
                'label' => 'string!',
                'icon' => 'string',
                'count' => 'integer!',
                'has_more' => 'boolean!',
                'more_url' => 'string',
                'items' => '@SearchItem[]',
            ],
            'SearchItem' => [
                'id' => 'string!',                                  // string|number — сводим к string
                'title' => 'string!',
                'subtitle' => 'string',
                'icon' => 'string',
                'url' => 'string!',
                'meta' => 'object',
                'score' => 'number',                                   // только для Scout
            ],

            'SearchUnavailableResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                       // errorKey=search_unavailable
            ],

            /* ------------------------------------------------------------------
             * Health (laravel-admin-health)
             * ------------------------------------------------------------------ */

            'HealthSummaryResponse' => [
                'success' => 'boolean!',
                'payload' => '@HealthSummaryPayload',
            ],
            'HealthSummaryPayload' => [
                'overall' => 'string!',                           // ok|warning|failing
                'counts' => '@HealthCounts',
                'last_run_at' => 'string(date-time)!',
                'failing_checks' => '@HealthFailingItem[]',
            ],
            'HealthCounts' => [
                'ok' => 'integer!',
                'warning' => 'integer!',
                'failing' => 'integer!',
            ],
            'HealthFailingItem' => [
                'id' => 'string!',
                'name' => 'string!',
                'message' => 'string!',
            ],

            'HealthChecksResponse' => [
                'success' => 'boolean!',
                'payload' => '@HealthChecksPayload',
            ],
            'HealthChecksPayload' => [
                'checks' => '@HealthCheckStatus[]',
                'last_run_at' => 'string(date-time)!',
            ],
            'HealthCheckStatus' => [
                'id' => 'string!',
                'name' => 'string!',
                'category' => 'string!',                               // database|cache|queue|storage|custom
                'status' => 'string!',                               // ok|warning|failing
                'message' => 'string',
                'meta' => 'object!',
                'frequency' => 'string!',                               // 1m|5m|1h
                'last_run_at' => 'string(date-time)!',
                'duration_ms' => 'integer!',
            ],

            'HealthCheckStatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@HealthCheckStatus',
            ],

            'HealthHistoryResponse' => [
                'success' => 'boolean!',
                'payload' => '@HealthHistoryPayload',
            ],
            'HealthHistoryPayload' => [
                'data' => '@HealthHistoryItem[]',
                'meta' => '@PaginationMeta',
            ],
            'HealthHistoryItem' => [
                'ran_at' => 'string(date-time)!',
                'status' => 'string!',
                'duration_ms' => 'integer!',
                'message' => 'string',
            ],
        ];
    }
}
