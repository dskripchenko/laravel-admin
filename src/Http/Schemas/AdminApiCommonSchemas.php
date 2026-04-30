<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * Common response templates: универсальные envelope-ответы и ошибки,
 * плюс building blocks (типовые объекты, переиспользуемые в других схемах).
 *
 * Включён в AdminApi.
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
             * Envelope-обёртки
             * ------------------------------------------------------------------ */

            // 200 OK с пустым payload: { success: true, payload: null }
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
                'uuid'     => 'string(uuid)!',
                'status'   => 'string!',                             // new|running|done|failed|cancelled|expired
                'progress' => 'integer',
                'message'  => 'string',
            ],

            // 304 Not Modified — без тела. Объявлено только для @response 304 {NotModifiedResponse}.
            'NotModifiedResponse' => [
                // empty body; etag в заголовке
            ],

            // 200 OK с типовой "скачать файл" семантикой (Content-Disposition: attachment).
            'FileDownloadResponse' => [
                // body — бинарный файл, OpenAPI описан как application/octet-stream через
                // глобальный consumes/produces; @output здесь не описывается.
            ],

            /* ------------------------------------------------------------------
             * Ошибки
             * ------------------------------------------------------------------ */

            'ValidationErrorResponse' => [
                'success' => 'boolean!',
                'payload' => '@ValidationErrorPayload',
            ],
            'ValidationErrorPayload' => [
                'errorKey' => 'string!',                             // 'validation'
                'message'  => 'string!',
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

            // 409 Conflict для optimistic concurrency: содержит свежий record.
            'ConflictResponse' => [
                'success' => 'boolean!',
                'payload' => '@ConflictPayload',
            ],
            'ConflictPayload' => [
                'errorKey' => 'string!',                             // 'conflict'
                'message'  => 'string!',
                'current'  => 'object',                              // свежее состояние записи
            ],

            // Универсальный shape простой ошибки.
            'SimpleErrorPayload' => [
                'errorKey' => 'string!',
                'message'  => 'string!',
            ],

            /* ------------------------------------------------------------------
             * Building blocks: переиспользуемые объекты, на которые ссылаются
             * остальные templates через @-references.
             * ------------------------------------------------------------------ */

            // Сводный пользовательский summary, возвращается из system.me, auth.login и т.д.
            'AdminUserSummary' => [
                'id'                => 'integer!',
                'name'              => 'string!',
                'email'             => 'string(email)!',
                'avatar'            => 'string',
                'locale'            => 'string!',
                'theme'             => 'string!',                    // light|dark
                'twoFactorEnabled'  => 'boolean!',
                'impersonator'      => '@ImpersonatorRef',
            ],
            'ImpersonatorRef' => [
                'id'   => 'integer!',
                'name' => 'string!',
            ],

            // Запись audit-журнала.
            'AuditLogEntry' => [
                'id'           => 'integer!',
                'user'         => '@AuditUserRef',
                'event'        => 'string!',
                'subject_type' => 'string',
                'subject_id'   => 'string',                          // string|number — храним как строку для OpenAPI
                'attributes'   => 'object',
                'old'          => 'object',
                'new'          => 'object',
                'ip'           => 'string',
                'user_agent'   => 'string',
                'created_at'   => 'string(date-time)!',
            ],
            'AuditUserRef' => [
                'id'    => 'integer!',
                'name'  => 'string!',
                'email' => 'string(email)!',
            ],

            // Описание поля (формы) — для manifest и для resource.meta.
            'FieldSchema' => [
                'name'         => 'string!',
                'type'         => 'string!',
                'label'        => 'string!',
                'placeholder'  => 'string',
                'help'         => 'string',
                'required'     => 'boolean!',
                'rules'        => 'array!',                          // string[] либо object[]
                'options'      => 'object!',                         // type-specific
                'visibility'   => '@FieldVisibility',
                'reactive'     => '@FieldReactive',
                'defaultValue' => 'object',
            ],
            'FieldVisibility' => [
                'create' => 'boolean!',
                'update' => 'boolean!',
                'view'   => 'boolean!',
            ],
            'FieldReactive' => [
                'reloadFor' => 'array!',                             // string[]
                'endpoint'  => 'string!',
            ],

            // Описание колонки таблицы.
            'ColumnSchema' => [
                'name'          => 'string!',
                'label'         => 'string!',
                'type'          => 'string!',
                'sortable'      => 'boolean!',
                'searchable'    => 'boolean!',
                'copyable'      => 'boolean!',
                'width'         => 'string',
                'defaultHidden' => 'boolean!',
                'cantHide'      => 'boolean!',
                'align'         => 'string!',                        // left|center|right
                'editable'      => '@ColumnEditable',
                'summary'       => 'array',                          // string[]: sum|avg|count|range
                'preset'        => 'string',
                'meta'          => 'object',
            ],
            'ColumnEditable' => [
                'field'      => 'string!',
                'validation' => 'array!',
            ],

            // Описание фильтра.
            'FilterSchema' => [
                'name'     => 'string!',
                'label'    => 'string!',
                'type'     => 'string!',                             // input|switcher|date_range|select_from_*
                'options'  => 'array',                               // {value,label}[]
                'default'  => 'object',
                'multiple' => 'boolean!',
            ],

            // Описание action'а.
            'ActionSchema' => [
                'name'        => 'string!',
                'label'       => 'string!',
                'icon'        => 'string',
                'type'        => 'string!',                          // button|link|modal|bulk|async|export|...
                'confirm'     => '@ActionConfirm',
                'permission'  => 'string',
                'primary'     => 'boolean!',
                'destructive' => 'boolean!',
                'position'    => 'array!',                           // string[] из command_bar|row|bulk|header
                'endpoint'    => 'string',
                'parameters'  => '@FieldSchema[]',
            ],
            'ActionConfirm' => [
                'message' => 'string!',
                'title'   => 'string!',
            ],

            // Описание layout-слоя (рекурсивная структура).
            'LayoutSchema' => [
                'id'       => 'string!',
                'type'     => 'string!',                             // rows|columns|tabs|accordion|modal|drawer|block|table|metrics|chart|wizard|infolist|view|wrapper
                'props'    => 'object!',
                'children' => '@LayoutSchema[]',                     // вложенные слои
            ],

            // Запись меню сайдбара.
            'MenuItem' => [
                'key'      => 'string!',
                'label'    => 'string!',
                'icon'     => 'string',
                'url'      => 'string',
                'badge'    => 'string',
                'children' => '@MenuItem[]',
                'order'    => 'integer!',
            ],

            // Группа permissions.
            'PermissionGroup' => [
                'name'  => 'string!',
                'items' => '@PermissionItem[]',
            ],
            'PermissionItem' => [
                'key'   => 'string!',
                'label' => 'string!',
            ],

            // Описание зарегистрированного AdminPlugin.
            'PluginManifest' => [
                'id'       => 'string!',
                'version'  => 'string!',
                'requires' => 'array!',                              // string[]
            ],

            /* ------------------------------------------------------------------
             * Пагинация (мета-блок, переиспользуемый везде)
             * ------------------------------------------------------------------ */

            'PaginationMeta' => [
                'page'      => 'integer!',
                'per_page'  => 'integer!',
                'total'     => 'integer!',
                'last_page' => 'integer!',
                'from'      => 'integer',
                'to'        => 'integer',
            ],
        ];
    }
}
