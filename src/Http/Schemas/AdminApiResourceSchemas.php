<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * Templates для Resource-controllers, actions, Settings.
 */
trait AdminApiResourceSchemas
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function provideResourceSchemas(): array
    {
        return [

            /* ------------------------------------------------------------------
             * Resource: meta / search / read / create / update / delete
             * ------------------------------------------------------------------ */

            'ResourceMetaResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceMetaPayload',
            ],
            'ResourceMetaPayload' => [
                'fields' => '@FieldSchema[]',
                'columns' => '@ColumnSchema[]',
                'filters' => '@FilterSchema[]',
                'actions' => '@ActionSchema[]',
                'permissions' => 'object!',                            // map<string,bool>
                'features' => '@ResourceFeatures',
            ],
            'ResourceFeatures' => [
                'softDeletes' => 'boolean!',
                'replicable' => 'boolean!',
                'reorderable' => 'object',                    // {column} | false
                'importable' => 'boolean!',
                'exportable' => 'array!',                    // ('csv'|'xlsx'|'pdf')[]
                'polling' => 'string',
                'warnOnUnsavedChanges' => 'boolean!',
            ],

            'ResourceSearchResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceSearchPayload',
            ],
            'ResourceSearchPayload' => [
                'data' => 'array!',                                    // object[] — структура зависит от Resource
                'meta' => '@ResourceSearchMeta',
            ],
            'ResourceSearchMeta' => [
                'page' => 'integer!',
                'per_page' => 'integer!',
                'total' => 'integer!',
                'last_page' => 'integer!',
                'from' => 'integer',
                'to' => 'integer',
                'summary' => 'object',                               // footer-агрегаты
                'groups' => 'array',                                // group-by markers
            ],

            'ResourceReadResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceReadPayload',
            ],
            'ResourceReadPayload' => [
                'record' => 'object!',
                'state' => 'object!',
                'permissions' => '@ResourceRecordPermissions',
                'audit_summary' => '@ResourceAuditSummary',
                'etag' => 'string!',
            ],
            'ResourceRecordPermissions' => [
                'update' => 'boolean!',
                'delete' => 'boolean!',
                'force_delete' => 'boolean!',
                'restore' => 'boolean!',
                'replicate' => 'boolean!',
            ],
            'ResourceAuditSummary' => [
                'created_by' => '@AuditUserRef',
                'created_at' => 'string(date-time)!',
                'updated_by' => '@AuditUserRef',
                'updated_at' => 'string(date-time)!',
                'deleted_at' => 'string(date-time)',
                'audit_count' => 'integer!',
            ],

            'ResourceCreatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceCreatedPayload',
            ],
            'ResourceCreatedPayload' => [
                'record' => 'object!',
                'redirect_url' => 'string!',
                'message' => 'string!',
            ],

            'ResourceUpdatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceUpdatedPayload',
            ],
            'ResourceUpdatedPayload' => [
                'record' => 'object!',
                'state' => 'object!',
                'etag' => 'string!',
                'message' => 'string!',
            ],

            'ResourceDeletedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceDeletedPayload',
            ],
            'ResourceDeletedPayload' => [
                'record' => 'object',
                'message' => 'string!',
            ],

            'ResourceRestoredResponse' => [
                'success' => 'boolean!',
                'payload' => '@ResourceRestoredPayload',
            ],
            'ResourceRestoredPayload' => [
                'record' => 'object!',
                'message' => 'string!',
            ],

            'InlineEditResponse' => [
                'success' => 'boolean!',
                'payload' => '@InlineEditPayload',
            ],
            'InlineEditPayload' => [
                'record' => 'object!',                                // только id и обновлённое поле
                'message' => 'string',
            ],

            /* ------------------------------------------------------------------
             * Resource: view (infolist), audit, reactiveField
             * ------------------------------------------------------------------ */

            'InfolistResponse' => [
                'success' => 'boolean!',
                'payload' => '@InfolistPayload',
            ],
            'InfolistPayload' => [
                'record' => 'object!',
                'layout' => '@LayoutSchema[]',
                'etag' => 'string!',
            ],

            'ReactiveFieldResponse' => [
                'success' => 'boolean!',
                'payload' => '@ReactiveFieldPayload',
            ],
            'ReactiveFieldPayload' => [
                'field' => 'string!',
                'options' => 'array',                                  // {value,label}[]
                'value' => 'object',
                'visible' => 'boolean',
                'rules' => 'array',
            ],

            /* ------------------------------------------------------------------
             * Resource: relations
             * ------------------------------------------------------------------ */

            'RelationAttachedResponse' => [
                'success' => 'boolean!',
                'payload' => '@RelationAttachedPayload',
            ],
            'RelationAttachedPayload' => [
                'related' => 'object!',                                // object|array
                'message' => 'string!',
            ],

            'RelationSyncResponse' => [
                'success' => 'boolean!',
                'payload' => '@RelationSyncPayload',
            ],
            'RelationSyncPayload' => [
                'attached' => 'integer!',
                'detached' => 'integer!',
            ],

            /* ------------------------------------------------------------------
             * Resource: saved views, preferences
             * ------------------------------------------------------------------ */

            'SavedViewsListResponse' => [
                'success' => 'boolean!',
                'payload' => '@SavedViewsListPayload',
            ],
            'SavedViewsListPayload' => [
                'data' => '@SavedView[]',
            ],
            'SavedView' => [
                'id' => 'integer!',
                'name' => 'string!',
                'payload' => '@SavedViewPayloadData',
                'is_shared' => 'boolean!',
                'is_default' => 'boolean!',
                'owner' => '@AuditUserRef',
                'created_at' => 'string(date-time)!',
            ],
            'SavedViewPayloadData' => [
                'filter' => 'object!',
                'sort' => 'string',
                'columns' => 'array',                                 // string[]
                'group_by' => 'string',
                'per_page' => 'integer',
            ],

            'SavedViewResponse' => [
                'success' => 'boolean!',
                'payload' => '@SavedViewPayloadWrapper',
            ],
            'SavedViewPayloadWrapper' => [
                'view' => '@SavedView',
            ],

            'TablePreferencesResponse' => [
                'success' => 'boolean!',
                'payload' => '@TablePreferencesPayload',
            ],
            'TablePreferencesPayload' => [
                'preferences' => '@TablePreferences',
            ],
            'TablePreferences' => [
                'columns' => '@TablePreferencesColumn[]',
                'per_page' => 'integer',
            ],
            'TablePreferencesColumn' => [
                'name' => 'string!',
                'visible' => 'boolean!',
                'order' => 'integer!',
            ],

            /* ------------------------------------------------------------------
             * Actions (bulk / single / parameters)
             * ------------------------------------------------------------------ */

            'BulkActionResponse' => [
                'success' => 'boolean!',
                'payload' => '@BulkActionPayload',
            ],
            'BulkActionPayload' => [
                'affected' => 'integer!',
                'message' => 'string!',
                'refresh' => 'boolean!',
                'failed' => '@BulkActionFailedItem[]',
            ],
            'BulkActionFailedItem' => [
                'id' => 'string!',
                'error' => 'string!',
            ],

            'SingleActionResponse' => [
                'success' => 'boolean!',
                'payload' => '@SingleActionPayload',
            ],
            'SingleActionPayload' => [
                'record' => 'object',
                'message' => 'string!',
                'redirect_url' => 'string',
                'refresh' => 'boolean!',
                'download_url' => 'string',
            ],

            'ActionParametersResponse' => [
                'success' => 'boolean!',
                'payload' => '@ActionParametersPayload',
            ],
            'ActionParametersPayload' => [
                'title' => 'string!',
                'description' => 'string',
                'fields' => '@FieldSchema[]',
                'submit_label' => 'string!',
                'cancel_label' => 'string!',
                'confirm' => '@ActionConfirm',
            ],

            /* ------------------------------------------------------------------
             * Settings (singleton-Resource)
             * ------------------------------------------------------------------ */

            'SettingsMetaResponse' => [
                'success' => 'boolean!',
                'payload' => '@SettingsMetaPayload',
            ],
            'SettingsMetaPayload' => [
                'fields' => '@FieldSchema[]',
                'layout' => '@LayoutSchema[]',
                'permissions' => '@SettingsPermissions',
            ],
            'SettingsPermissions' => [
                'update' => 'boolean!',
            ],

            'SettingsShowResponse' => [
                'success' => 'boolean!',
                'payload' => '@SettingsShowPayload',
            ],
            'SettingsShowPayload' => [
                'state' => 'object!',
                'layout' => '@LayoutSchema[]',
                'fields' => '@FieldSchema[]',
                'permissions' => '@SettingsPermissions',
                'etag' => 'string!',
            ],

            'SettingsUpdateResponse' => [
                'success' => 'boolean!',
                'payload' => '@SettingsUpdatePayload',
            ],
            'SettingsUpdatePayload' => [
                'state' => 'object!',
                'etag' => 'string!',
                'message' => 'string!',
                'affected_keys' => 'array!',                           // string[]
            ],
        ];
    }
}
