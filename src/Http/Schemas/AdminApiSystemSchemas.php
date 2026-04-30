<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\Schemas;

/**
 * Templates для контроллеров system, auth, profile.
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
