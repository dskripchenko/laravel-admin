<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * The common response templates: the generic envelopes and errors, plus the
 * building blocks — the typical objects other schemas reuse.
 *
 * Included in AdminApi.
 */
trait AdminApiCommonSchemas
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function provideCommonSchemas(): array
    {
        return [

            /* ------------------------------------------------------------------
             * The envelopes
             * ------------------------------------------------------------------ */

            // 200 OK with an empty payload: { success: true, payload: null }
            'SuccessResponse' => [
                'success' => 'boolean!',
                'payload' => 'object',                              // nullable
            ],

            // { success: true, payload: { affected: int } }
            'AffectedResponse' => [
                'success' => 'boolean!',
                'payload' => '@AffectedPayload',
            ],
            'AffectedPayload' => [
                'affected' => 'integer!',
            ],

            // { success: true, payload: { message: string } }
            'GenericMessageResponse' => [
                'success' => 'boolean!',
                'payload' => '@GenericMessagePayload',
            ],
            'GenericMessagePayload' => [
                'message' => 'string!',
            ],

            // 202 Accepted: { success: true, payload: { delayed: {...} } }
            'DelayedResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedPayload',
            ],
            'DelayedPayload' => [
                'delayed' => '@DelayedHandle',
            ],
            'DelayedHandle' => [
                'uuid' => 'string(uuid)!',
                'status' => 'string!',                             // new|running|done|failed|cancelled|expired
                'progress' => 'integer',
                'message' => 'string',
            ],

            // 304 Not Modified, with no body. Declared only for @response 304 {NotModifiedResponse}.
            'NotModifiedResponse' => [
                // An empty body; the etag is in the header
            ],

            // 200 OK with the usual "download a file" semantics (Content-Disposition: attachment).
            'FileDownloadResponse' => [
                // The body is a binary file; OpenAPI describes it as
                // application/octet-stream through the global consumes and
                // produces, so there is no @output here.
            ],

            /* ------------------------------------------------------------------
             * The errors
             * ------------------------------------------------------------------ */

            'ValidationErrorResponse' => [
                'success' => 'boolean!',
                'payload' => '@ValidationErrorPayload',
            ],
            'ValidationErrorPayload' => [
                'errorKey' => 'string!',                             // 'validation'
                'message' => 'string!',
                'messages' => 'object!',                             // field => string[]
            ],

            'UnauthenticatedErrorResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'ForbiddenErrorResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'NotFoundErrorResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'ThrottledResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'MethodNotAllowedResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'PayloadTooLargeResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],
            'UnsupportedMediaTypeResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],

            // 409 Conflict for optimistic concurrency: it carries the fresh record.
            'ConflictResponse' => [
                'success' => 'boolean!',
                'payload' => '@ConflictPayload',
            ],
            'ConflictPayload' => [
                'errorKey' => 'string!',                             // 'conflict'
                'message' => 'string!',
                'current' => 'object',                              // свежее состояние записи
            ],

            // The generic shape of a simple error.
            'SimpleErrorPayload' => [
                'errorKey' => 'string!',
                'message' => 'string!',
            ],

            /* ------------------------------------------------------------------
             * The building blocks: the reusable objects the other templates
             * point at through @-references.
             * ------------------------------------------------------------------ */

            // The user summary, returned from system.me, auth.login and the rest.
            'AdminUserSummary' => [
                'id' => 'integer!',
                'name' => 'string!',
                'email' => 'string(email)!',
                'avatar' => 'string',
                'locale' => 'string!',
                'theme' => 'string!',                    // light|dark
                'twoFactorEnabled' => 'boolean!',
                'impersonator' => '@ImpersonatorRef',
            ],
            'ImpersonatorRef' => [
                'id' => 'integer!',
                'name' => 'string!',
            ],

            // An entry of the audit log.
            'AuditLogEntry' => [
                'id' => 'integer!',
                'user' => '@AuditUserRef',
                'event' => 'string!',
                'subject_type' => 'string',
                'subject_id' => 'string',                          // string|number — храним как строку для OpenAPI
                'attributes' => 'object',
                'old' => 'object',
                'new' => 'object',
                'ip' => 'string',
                'user_agent' => 'string',
                'created_at' => 'string(date-time)!',
            ],
            'AuditUserRef' => [
                'id' => 'integer!',
                'name' => 'string!',
                'email' => 'string(email)!',
            ],

            // A form field's description, for the manifest and for resource.meta.
            'FieldSchema' => [
                'name' => 'string!',
                'type' => 'string!',
                'label' => 'string!',
                'placeholder' => 'string',
                'help' => 'string',
                'required' => 'boolean!',
                'rules' => 'array!',                          // string[] либо object[]
                'options' => 'object!',                         // type-specific
                'visibility' => '@FieldVisibility',
                'reactive' => '@FieldReactive',
                'defaultValue' => 'object',
            ],
            'FieldVisibility' => [
                'create' => 'boolean!',
                'update' => 'boolean!',
                'view' => 'boolean!',
            ],
            'FieldReactive' => [
                'reloadFor' => 'array!',                             // string[]
                'endpoint' => 'string!',
            ],

            // A table column's description.
            'ColumnSchema' => [
                'name' => 'string!',
                'label' => 'string!',
                'type' => 'string!',
                'sortable' => 'boolean!',
                'searchable' => 'boolean!',
                'copyable' => 'boolean!',
                'width' => 'string',
                'defaultHidden' => 'boolean!',
                'cantHide' => 'boolean!',
                'align' => 'string!',                        // left|center|right
                'editable' => '@ColumnEditable',
                'summary' => 'array',                          // string[]: sum|avg|count|range
                'preset' => 'string',
                'meta' => 'object',
            ],
            'ColumnEditable' => [
                'field' => 'string!',
                'validation' => 'array!',
            ],

            // A filter's description.
            'FilterSchema' => [
                'name' => 'string!',
                'label' => 'string!',
                'type' => 'string!',                             // input|switcher|date_range|select_from_*
                'options' => 'array',                               // {value,label}[]
                'default' => 'object',
                'multiple' => 'boolean!',
            ],

            // An action's description.
            'ActionSchema' => [
                'name' => 'string!',
                'label' => 'string!',
                'icon' => 'string',
                'type' => 'string!',                          // button|link|modal|bulk|async|export|...
                'confirm' => '@ActionConfirm',
                'permission' => 'string',
                'primary' => 'boolean!',
                'destructive' => 'boolean!',
                'position' => 'array!',                           // string[] из command_bar|row|bulk|header
                'endpoint' => 'string',
                'parameters' => '@FieldSchema[]',
            ],
            'ActionConfirm' => [
                'message' => 'string!',
                'title' => 'string!',
            ],

            // A layout node's description; the structure is recursive.
            'LayoutSchema' => [
                'id' => 'string!',
                'type' => 'string!',                             // rows|columns|tabs|accordion|modal|drawer|block|table|metrics|chart|wizard|infolist|view|wrapper
                'props' => 'object!',
                'children' => '@LayoutSchema[]',                     // вложенные слои
            ],

            // An entry of the sidebar menu.
            'MenuItem' => [
                'key' => 'string!',
                'label' => 'string!',
                'icon' => 'string',
                'url' => 'string',
                'badge' => 'string',
                'children' => '@MenuItem[]',
                'order' => 'integer!',
            ],

            // A group of permissions.
            'PermissionGroup' => [
                'name' => 'string!',
                'items' => '@PermissionItem[]',
            ],
            'PermissionItem' => [
                'key' => 'string!',
                'label' => 'string!',
            ],

            // A registered AdminPlugin's description.
            'PluginManifest' => [
                'id' => 'string!',
                'version' => 'string!',
                'requires' => 'array!',                              // string[]
            ],

            /* ------------------------------------------------------------------
             * Pagination — the meta block reused everywhere
             * ------------------------------------------------------------------ */

            'PaginationMeta' => [
                'page' => 'integer!',
                'per_page' => 'integer!',
                'total' => 'integer!',
                'last_page' => 'integer!',
                'from' => 'integer',
                'to' => 'integer',
            ],
        ];
    }
}
