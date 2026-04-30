<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * Templates для UI-слоя: Screens, Dashboards/Widgets, Uploads, Delayed processes,
 * Exports/Imports.
 */
trait AdminApiUiSchemas
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function provideUiSchemas(): array
    {
        return [

            /* ------------------------------------------------------------------
             * Screens (state / runMethod / async)
             * ------------------------------------------------------------------ */

            'ScreenStateResponse' => [
                'success' => 'boolean!',
                'payload' => '@ScreenStatePayload',
            ],
            'ScreenStatePayload' => [
                'state' => 'object!',
                'name' => 'string!',
                'description' => 'string',
                'layout' => '@LayoutSchema[]',
                'command_bar' => '@ActionSchema[]',
                'permissions' => 'array!',                              // string[]
                'etag' => 'string!',
            ],

            'ScreenMethodResponse' => [
                'success' => 'boolean!',
                'payload' => '@ScreenMethodPayload',
            ],
            'ScreenMethodPayload' => [
                'state' => 'object!',
                'layouts' => 'object',                             // map id => LayoutSchema
                'alerts' => '@ScreenAlert[]',
                'redirect_url' => 'string',
                'refresh' => 'boolean!',
                'download_url' => 'string',
                'message' => 'string!',
            ],
            'ScreenAlert' => [
                'type' => 'string!',                            // info|success|warning|danger
                'message' => 'string!',
                'duration_ms' => 'integer',
            ],

            'ScreenAsyncResponse' => [
                'success' => 'boolean!',
                'payload' => '@ScreenAsyncPayload',
            ],
            'ScreenAsyncPayload' => [
                'layouts' => 'object!',                             // map id => LayoutSchema
                'state_patch' => 'object',                              // частичный merge state
            ],

            /* ------------------------------------------------------------------
             * Dashboards и Widgets
             * ------------------------------------------------------------------ */

            'DashboardsListResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardsListPayload',
            ],
            'DashboardsListPayload' => [
                'data' => '@DashboardSummary[]',
                'default' => 'string',
            ],
            'DashboardSummary' => [
                'slug' => 'string!',
                'title' => 'string!',
                'description' => 'string',
                'icon' => 'string',
                'url' => 'string!',
                'permission' => 'string',
                'is_customizable' => 'boolean!',
            ],

            'DashboardShowResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardShowPayload',
            ],
            'DashboardShowPayload' => [
                'dashboard' => '@DashboardSummary',
                'widgets' => '@WidgetInstance[]',
                'layout' => '@WidgetLayoutItem[]',
                'user_layout_saved_at' => 'string(date-time)',
            ],
            'WidgetInstance' => [
                'id' => 'string!',
                'type' => 'string!',
                'label' => 'string!',
                'description' => 'string',
                'url' => 'string!',
                'poll' => 'string',
                'permission' => 'string',
                'initial_data' => 'object',
                'options' => 'object!',
            ],
            'WidgetLayoutItem' => [
                'widget_id' => 'string!',
                'x' => 'integer!',
                'y' => 'integer!',
                'w' => 'integer!',
                'h' => 'integer!',
            ],

            'WidgetDataResponse' => [
                'success' => 'boolean!',
                'payload' => '@WidgetDataPayload',
            ],
            'WidgetDataPayload' => [
                'data' => 'object!',                         // type-specific
                'fetched_at' => 'string(date-time)!',
                'next_refresh_at' => 'string(date-time)',
            ],

            'LayoutSavedResponse' => [
                'success' => 'boolean!',
                'payload' => '@LayoutSavedPayload',
            ],
            'LayoutSavedPayload' => [
                'saved_at' => 'string(date-time)!',
            ],

            'DashboardCreatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardCreatedPayload',
            ],
            'DashboardCreatedPayload' => [
                'dashboard' => '@DashboardSummary',
                'redirect_url' => 'string!',
            ],

            /* ------------------------------------------------------------------
             * Uploads (single + chunked)
             * ------------------------------------------------------------------ */

            'UploadCreatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@UploadCreatedPayload',
            ],
            'UploadCreatedPayload' => [
                'upload' => '@AdminUpload',
            ],
            'AdminUpload' => [
                'id' => 'string(uuid)!',
                'url' => 'string!',
                'preview_url' => 'string',
                'mime' => 'string!',
                'size' => 'integer!',
                'original_name' => 'string!',
                'width' => 'integer',
                'height' => 'integer',
                'collection' => 'string',
                'created_at' => 'string(date-time)!',
            ],

            'UploadShowResponse' => [
                'success' => 'boolean!',
                'payload' => '@UploadCreatedPayload',                   // тот же shape
            ],

            'ChunkedStartResponse' => [
                'success' => 'boolean!',
                'payload' => '@ChunkedStartPayload',
            ],
            'ChunkedStartPayload' => [
                'upload_id' => 'string(uuid)!',
                'chunk_endpoint' => 'string!',
                'finish_endpoint' => 'string!',
                'expires_at' => 'string(date-time)!',
            ],

            'ChunkAcceptedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ChunkAcceptedPayload',
            ],
            'ChunkAcceptedPayload' => [
                'received' => 'integer!',
                'total' => 'integer!',
                'next_index' => 'integer',
            ],

            'ChunkChecksumMismatchResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                     // errorKey=chunk_checksum_mismatch
            ],

            /* ------------------------------------------------------------------
             * Delayed processes
             * ------------------------------------------------------------------ */

            'DelayedStatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedStatusPayload',
            ],
            'DelayedStatusPayload' => [
                'processes' => '@DelayedProcessStatus[]',
            ],
            'DelayedProcessStatus' => [
                'uuid' => 'string(uuid)!',
                'status' => 'string!',                              // new|running|done|failed|cancelled|expired
                'progress' => 'integer!',
                'message' => 'string',
                'started_at' => 'string(date-time)',
                'finished_at' => 'string(date-time)',
                'duration_ms' => 'integer',
                'attempts' => 'integer!',
                'data' => 'object',                               // финальный payload, когда status=done
                'error' => '@DelayedProcessError',
            ],
            'DelayedProcessError' => [
                'class' => 'string!',
                'message' => 'string!',
            ],

            'DelayedCancelResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedCancelPayload',
            ],
            'DelayedCancelPayload' => [
                'status' => 'string!',                                   // cancelled|finishing
            ],

            'DelayedListResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedListPayload',
            ],
            'DelayedListPayload' => [
                'data' => '@DelayedProcessStatus[]',
                'meta' => '@PaginationMeta',
            ],

            'CannotCancelResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                      // errorKey=cannot_cancel
            ],

            /* ------------------------------------------------------------------
             * Exports & Imports
             * ------------------------------------------------------------------ */

            'MissingExportDriverResponse' => [
                'success' => 'boolean!',
                'payload' => '@MissingExportDriverPayload',
            ],
            'MissingExportDriverPayload' => [
                'errorKey' => 'string!',                                 // 'missing_export_driver'
                'message' => 'string!',
                'command' => 'string',                                  // composer require ...
            ],

            'InvalidImportFileResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                      // errorKey=invalid_import_file
            ],

            'ImportUploadResponse' => [
                'success' => 'boolean!',
                'payload' => '@ImportUploadPayload',
            ],
            'ImportUploadPayload' => [
                'upload_id' => 'string(uuid)!',
                'columns_detected' => 'array!',                       // string[]
                'sample_rows' => 'array!',                       // object[]
                'total_rows_estimate' => 'integer!',
                'target_fields' => '@ImportTargetField[]',
                'auto_mapping' => 'object!',                      // file_column => resource_field|null
            ],
            'ImportTargetField' => [
                'name' => 'string!',
                'label' => 'string!',
                'required' => 'boolean!',
            ],

            'ImportPreviewResponse' => [
                'success' => 'boolean!',
                'payload' => '@ImportPreviewPayload',
            ],
            'ImportPreviewPayload' => [
                'preview' => '@ImportPreviewRow[]',
                'summary' => '@ImportPreviewSummary',
            ],
            'ImportPreviewRow' => [
                'row_number' => 'integer!',
                'status' => 'string!',                                // create|update|skip|fail
                'data' => 'object!',
                'errors' => 'object',                                 // field => string[]
            ],
            'ImportPreviewSummary' => [
                'total' => 'integer!',
                'will_create' => 'integer!',
                'will_update' => 'integer!',
                'will_skip' => 'integer!',
                'will_fail' => 'integer!',
            ],
        ];
    }
}
