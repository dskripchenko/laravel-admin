<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * The templates of the system, auth and profile controllers.
 */
trait AdminApiSystemSchemas
{
    /**
     * @return array<string, array<string, string>>
     */
    public static function provideSystemSchemas(): array
    {
        return [

            /* ------------------------------------------------------------------
             * The endpoints whose @response named a template nobody declared.
             *
             * Found by `api:lint` from laravel-api 5.7.0: the docblocks named
             * 33 templates that did not exist, so the published spec carried
             * `$ref`s into nothing — valid OpenAPI that no generated client can
             * follow. The shapes below are read off the controllers, not
             * guessed: each mirrors what the action actually returns.
             * ------------------------------------------------------------------ */

            'LocaleUpdatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@LocaleUpdatedPayload',
            ],
            'LocaleUpdatedPayload' => [
                'locale' => 'string! The locale now in force; also set as a cookie',
            ],

            'ThemeStateResponse' => [
                'success' => 'boolean!',
                'payload' => '@ThemeStatePayload',
            ],
            'ThemeStatePayload' => [
                'current' => 'string!',
                'default' => 'string!',
                'available' => 'array! The theme names this installation offers',
            ],

            'ThemeUpdatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ThemeUpdatedPayload',
            ],
            'ThemeUpdatedPayload' => [
                'theme' => 'string! The theme now in force; also set as a cookie',
            ],

            'StatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@StatusPayload',
            ],
            'StatusPayload' => [
                'indicators' => '@StatusIndicatorState[] Empty while every indicator reports ok',
            ],
            'StatusIndicatorState' => [
                'key' => 'string! A stable identifier, admin.health and the like',
                'status' => 'string! ok|warning|error|unknown',
                'label' => 'string! A word or two, drawn beside the dot',
                'detail' => 'string The tooltip',
                'url' => 'string Where a click leads, when there is somewhere to go',
            ],

            'SettingsReadResponse' => [
                'success' => 'boolean!',
                'payload' => '@SettingsReadPayload',
            ],
            'SettingsReadPayload' => [
                'values' => 'object! Key to value, as stored',
            ],

            'SettingsUpdatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@SettingsUpdatedPayload',
            ],
            'SettingsUpdatedPayload' => [
                'values' => 'object! Re-read after the write, not echoed back',
                'message' => 'string!',
            ],

            'AuditTimelineResponse' => [
                'success' => 'boolean!',
                'payload' => '@AuditTimelinePayload',
            ],
            'AuditTimelinePayload' => [
                'data' => 'array! Entries grouped into a timeline by AuditTimelineProjector',
            ],

            'DelayedProcessRunResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedProcessRunPayload',
            ],
            'DelayedProcessRunPayload' => [
                'uuid' => 'string(uuid)! The handle to ask about later',
                'status' => 'string! new|running|done|failed|cancelled|expired',
            ],

            'DelayedProcessStatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@DelayedProcessStatus',
            ],

            'UploadResponse' => [
                'success' => 'boolean!',
                'payload' => '@UploadPayload',
            ],
            'UploadPayload' => [
                'disk' => 'string The disk it landed on; null when nothing was sent',
                'path' => 'string',
                'url' => 'string',
                'name' => 'string The original file name',
                'size' => 'integer!',
                'mime' => 'string',
            ],

            'ImportStartResponse' => [
                'success' => 'boolean!',
                'payload' => '@ImportProcessPayload',
            ],
            'ImportStatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@ImportProcessPayload',
            ],
            'ImportProcessPayload' => [
                'process' => 'object! The import process as ImportController::serialize() renders it',
            ],

            'NotificationListResponse' => [
                'success' => 'boolean!',
                'payload' => '@NotificationListPayload',
            ],
            'NotificationListPayload' => [
                'data' => '@AdminNotification[]',
                'meta' => '@PaginationMeta',
            ],

            'NotificationUnreadResponse' => [
                'success' => 'boolean!',
                'payload' => '@NotificationUnreadPayload',
            ],
            'NotificationUnreadPayload' => [
                'count' => 'integer!',
                'data' => '@AdminNotification[] The most recent unread ones',
            ],

            'NotificationMarkResponse' => [
                'success' => 'boolean!',
                'payload' => '@NotificationMarkPayload',
            ],
            'NotificationMarkPayload' => [
                'id' => 'string(uuid)!',
                'unread_count' => 'integer! Recounted after the write',
            ],

            'NotificationMarkAllResponse' => [
                'success' => 'boolean!',
                'payload' => '@NotificationMarkAllPayload',
            ],
            'NotificationMarkAllPayload' => [
                'updated' => 'integer! How many were still unread',
                'unread_count' => 'integer!',
            ],

            'SavedViewListResponse' => [
                'success' => 'boolean!',
                'payload' => '@SavedViewListPayload',
            ],
            'SavedViewListPayload' => [
                'data' => '@SavedViewListItem[]',
            ],
            'SavedViewListItem' => [
                'id' => 'integer!',
                'name' => 'string!',
                'state' => 'object! The filters, sorting and columns the view restores',
                'is_default' => 'boolean!',
                'owned' => 'boolean! False for a view shared with everyone',
            ],

            'DashboardLayoutResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardLayoutPayload',
            ],
            'DashboardLayoutPayload' => [
                'layout' => 'array The per-user arrangement; null when never customised',
                'period' => 'string 7d|30d|90d|all',
            ],

            'DashboardLayoutSavedResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardLayoutSavedPayload',
            ],
            'DashboardLayoutSavedPayload' => [
                'id' => 'integer!',
                'widgets' => '@WidgetLayoutItem[]',
            ],

            'DashboardWidgetsResponse' => [
                'success' => 'boolean!',
                'payload' => '@DashboardWidgetsPayload',
            ],
            'DashboardWidgetsPayload' => [
                'widgets' => '@WidgetInstance[]',
                'period' => 'string! 7d|30d|90d|all',
            ],

            /* ------------------------------------------------------------------
             * system.*
             * ------------------------------------------------------------------ */

            'BootstrapResponse' => [
                'success' => 'boolean!',
                'payload' => '@BootstrapPayload',
            ],
            'BootstrapPayload' => [
                'csrf' => 'string!',
                'baseUrl' => 'string!',
                'apiUrl' => 'string!',
                'locale' => 'string!',
                'availableLocales' => 'array!',                       // string[]
                'theme' => 'string!',
                'brand' => '@BrandConfig',
                'user' => '@AdminUserSummary',
                'permissions' => 'array!',                        // string[]
                'manifestVersion' => 'string',
                'pluginVersions' => 'object!',                       // id => version
                'config' => 'object!',
            ],
            'BrandConfig' => [
                'name' => 'string!',
                'logo' => 'string',
                'favicon' => 'string',
            ],

            'ManifestResponse' => [
                'success' => 'boolean!',
                'payload' => '@ManifestPayload',
            ],
            'ManifestPayload' => [
                'version' => 'string!',
                'locale' => 'string!',
                'resources' => 'array!',                            // ResourceManifest[] — описано в schemas.md
                'screens' => 'array!',
                'settings' => 'array!',
                'dashboards' => 'array!',
                'plugins' => '@PluginManifest[]',
                'permissions' => '@PermissionGroup[]',
            ],

            'AdminUserSummaryResponse' => [
                'success' => 'boolean!',
                'payload' => '@AdminUserSummary',
            ],

            'MenuResponse' => [
                'success' => 'boolean!',
                'payload' => '@MenuPayload',
            ],
            'MenuPayload' => [
                'items' => '@MenuItem[]',
            ],

            'LocalesResponse' => [
                'success' => 'boolean!',
                'payload' => '@LocalesPayload',
            ],
            'LocalesPayload' => [
                'available' => 'array!',                              // string[]
                'current' => 'string!',
                'fallback' => 'string!',
            ],

            'PermissionsResponse' => [
                'success' => 'boolean!',
                'payload' => '@PermissionsPayload',
            ],
            'PermissionsPayload' => [
                'groups' => '@PermissionGroup[]',
            ],

            'PluginsResponse' => [
                'success' => 'boolean!',
                'payload' => '@PluginsPayload',
            ],
            'PluginsPayload' => [
                'plugins' => '@PluginManifest[]',
            ],

            'NotificationsListResponse' => [
                'success' => 'boolean!',
                'payload' => '@NotificationsListPayload',
            ],
            'NotificationsListPayload' => [
                'data' => '@AdminNotification[]',
                'meta' => '@PaginationMeta',
                'unread_count' => 'integer!',
            ],
            'AdminNotification' => [
                'id' => 'string(uuid)!',
                'type' => 'string!',
                'data' => '@AdminNotificationData',
                'read_at' => 'string(date-time)',
                'created_at' => 'string(date-time)!',
            ],
            'AdminNotificationData' => [
                'title' => 'string!',
                'message' => 'string!',
                'icon' => 'string',
                'color' => 'string',                            // info|success|warning|danger
                'action_url' => 'string',
                'action_label' => 'string',
            ],
            'NotificationItemResponse' => [
                'success' => 'boolean!',
                'payload' => '@AdminNotification',
            ],

            'AuditListResponse' => [
                'success' => 'boolean!',
                'payload' => '@AuditListPayload',
            ],
            'AuditListPayload' => [
                'data' => '@AuditLogEntry[]',
                'meta' => '@PaginationMeta',
            ],

            /* ------------------------------------------------------------------
             * auth.*
             * ------------------------------------------------------------------ */

            'LoginResponse' => [
                'success' => 'boolean!',
                'payload' => '@LoginPayload',
            ],
            'LoginPayload' => [
                'user' => '@AdminUserSummary',
                'redirect_url' => 'string!',
            ],

            'TwoFactorRequiredResponse' => [
                'success' => 'boolean!',                              // false
                'payload' => '@TwoFactorRequiredPayload',
            ],
            'TwoFactorRequiredPayload' => [
                'errorKey' => 'string!',                       // 'two_factor_required'
                'message' => 'string!',
                'challenge_token' => 'string!',
            ],

            'InvalidCredentialsResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                   // errorKey=invalid_credentials
            ],
            'AccountInactiveResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                   // errorKey=account_inactive
            ],
            'InvalidTwoFactorResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                   // errorKey=invalid_two_factor_code | challenge_expired
            ],
            'InvalidRecoveryCodeResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',
            ],

            'RecoveryLoginResponse' => [
                'success' => 'boolean!',
                'payload' => '@RecoveryLoginPayload',
            ],
            'RecoveryLoginPayload' => [
                'user' => '@AdminUserSummary',
                'redirect_url' => 'string!',
                'recovery_codes_remaining' => 'integer!',
            ],

            'ImpersonationResponse' => [
                'success' => 'boolean!',
                'payload' => '@ImpersonationPayload',
            ],
            'ImpersonationPayload' => [
                'user' => '@AdminUserSummary',
                'impersonator' => '@ImpersonatorRef',
                'redirect_url' => 'string!',
            ],

            'NoActiveImpersonationResponse' => [
                'success' => 'boolean!',
                'payload' => '@SimpleErrorPayload',                   // errorKey=no_active_impersonation
            ],

            /* ------------------------------------------------------------------
             * profile.*
             * ------------------------------------------------------------------ */

            'ProfileResponse' => [
                'success' => 'boolean!',
                'payload' => '@ProfilePayload',
            ],
            'ProfilePayload' => [
                'user' => '@AdminUserSummary',
                'available_locales' => 'array!',                      // string[]
                'available_themes' => 'array!',                      // string[]
                'two_factor' => '@ProfileTwoFactor',
                'api_tokens_enabled' => 'boolean!',
            ],
            'ProfileTwoFactor' => [
                'enabled' => 'boolean!',
                'confirmed_at' => 'string(date-time)',
                'recovery_codes_remaining' => 'integer!',
            ],

            'ProfileUpdateResponse' => [
                'success' => 'boolean!',
                'payload' => '@ProfileUpdatePayload',
            ],
            'ProfileUpdatePayload' => [
                'user' => '@AdminUserSummary',
            ],

            'TwoFactorStatusResponse' => [
                'success' => 'boolean!',
                'payload' => '@TwoFactorStatusPayload',
            ],
            'TwoFactorStatusPayload' => [
                'enabled' => 'boolean!',
                'confirmed_at' => 'string(date-time)',
                'qr_code_svg' => 'string',
                'secret' => 'string',
                'qr_uri' => 'string',
                'recovery_codes' => 'array',                         // string[]
            ],

            'TwoFactorSetupResponse' => [
                'success' => 'boolean!',
                'payload' => '@TwoFactorSetupPayload',
            ],
            'TwoFactorSetupPayload' => [
                'qr_code_svg' => 'string',                            // null если QR-encoder не подключён
                'secret' => 'string!',
                'qr_uri' => 'string!',
                'recovery_codes' => 'array!',                         // string[8]
            ],

            'TwoFactorConfirmedResponse' => [
                'success' => 'boolean!',
                'payload' => '@TwoFactorConfirmedPayload',
            ],
            'TwoFactorConfirmedPayload' => [
                'enabled' => 'boolean!',
                'confirmed_at' => 'string(date-time)!',
            ],

            'RecoveryCodesResponse' => [
                'success' => 'boolean!',
                'payload' => '@RecoveryCodesPayload',
            ],
            'RecoveryCodesPayload' => [
                'recovery_codes' => 'array!',                         // string[]
            ],

            'ApiTokenListResponse' => [
                'success' => 'boolean!',
                'payload' => '@ApiTokenListPayload',
            ],
            'ApiTokenListPayload' => [
                'data' => '@ApiToken[]',
            ],
            'ApiToken' => [
                'id' => 'integer!',
                'name' => 'string!',
                'abilities' => 'array!',                           // string[]
                'last_used_at' => 'string(date-time)',
                'created_at' => 'string(date-time)!',
                'expires_at' => 'string(date-time)',
            ],

            'ApiTokenCreatedResponse' => [
                'success' => 'boolean!',
                'payload' => '@ApiTokenCreatedPayload',
            ],
            'ApiTokenCreatedPayload' => [
                'token' => '@ApiToken',
                'plain_text_token' => 'string!',                      // показывается ОДИН раз
            ],
        ];
    }
}
