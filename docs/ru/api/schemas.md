# API: шаблоны ответов

Реестр named-шаблонов, на которые ссылаются теги `@response` в docblock'ах action'ов. Шаблоны отдаёт `AdminApi::getOpenApiTemplates()` (`src/Http/AdminApi.php`), сливая результаты пяти трейтов из `src/Http/Schemas/`. В OpenAPI-документе они становятся `components.schemas`.

> Механизм (`$useResponseTemplates`, синтаксис типов) — [registration.md §9](registration.md#9-шаблоны-ответов-для-response). Применение — в описаниях контроллеров ([system.md](system.md), [auth.md](auth.md), [resources.md](resources.md), ...).

Обозначения в столбце «Поля» — как в коде шаблона:

- `string!` — обязательное поле, `string` — необязательное;
- `string(format)` — OpenAPI-формат;
- `@Ref` — ссылка на другой шаблон, `@Ref[]` — массив ссылок.

Столбец «Где используется»:

- `controller/action (код)` — action, чей docblock ссылается на шаблон через `@response`. `{slug}` — контроллер ресурса, `{slug}_views` — сохранённые представления ресурса, `{screen}` — контроллер экрана, `settings_{slug}` — раздел настроек;
- **вложенный** — на шаблон ссылаются только другие используемые шаблоны;
- **не используется** — шаблон объявлен, но ни один `@response` в ядре на него не ссылается ни прямо, ни через вложенные ссылки. Такие шаблоны всё равно попадают в `components.schemas`, но описывают ответы, которых у эндпоинтов ядра нет (например, chunked-загрузка, отмена delayed-процессов, health-эндпоинты; шаблоны `Search*` не совпадают с фактическим ответом ни `system/search`, ни пакета `dskripchenko/laravel-admin-search`). Опираться на них при написании клиента не следует.

## Сводка

| Трейт | Метод | Всего | В `@response` | Вложенные | Не используются |
|---|---|---|---|---|---|
| `AdminApiCommonSchemas` | `provideCommonSchemas()` | 40 | 10 | 22 | 8 |
| `AdminApiSystemSchemas` | `provideSystemSchemas()` | 98 | 46 | 47 | 5 |
| `AdminApiResourceSchemas` | `provideResourceSchemas()` | 71 | 21 | 23 | 27 |
| `AdminApiUiSchemas` | `provideUiSchemas()` | 50 | 5 | 13 | 32 |
| `AdminApiSisterPackSchemas` | `provideSisterPackSchemas()` | 16 | 0 | 0 | 16 |
| **Итого** | | **275** | **82** | **105** | **88** |

Имена в трейтах не пересекаются, поэтому `array_merge()` в `getOpenApiTemplates()` ничего не перезаписывает. Несколько шаблонов дублируют друг друга по смыслу, и используется только один из пары: `NotificationListResponse` (используется) и `NotificationsListResponse` (нет), `SavedViewListResponse` и `SavedViewsListResponse`, `SettingsReadResponse`/`SettingsUpdatedResponse` и `SettingsShowResponse`/`SettingsUpdateResponse`, `GlobalSearchResponse` и `SearchResponse`, `UploadResponse` и `UploadCreatedResponse`, `ConflictErrorResponse` и `ConflictResponse`.

Пустые шаблоны `NotModifiedResponse` (304) и `FileDownloadResponse` (бинарный файл с `Content-Disposition: attachment`) тела в JSON не описывают.

## AdminApiCommonSchemas (40)

| Шаблон | Поля | Где используется |
|---|---|---|
| `SuccessResponse` | `success: boolean!, payload: object` | `dashboard/savePeriod` (200), `auth/logout` (200), `profile/changePassword` (200), `profile/twoFactorDisable` (200), `profile/tokenRevoke` (200), `{slug}_views/delete` (200), `notifications/destroy` (200) |
| `AffectedResponse` | `success: boolean!, payload: @AffectedPayload` | `{slug}/action` (200) |
| `AffectedPayload` | `affected: integer!, message: string!` | вложенный |
| `GenericMessageResponse` | `success: boolean!, payload: @GenericMessagePayload` | `auth/forgotPassword` (200), `auth/verifyEmail` (200), `auth/resendEmailVerification` (200) |
| `GenericMessagePayload` | `message: string!` | вложенный |
| `DelayedResponse` | `success: boolean!, payload: @DelayedPayload` | **не используется** |
| `DelayedPayload` | `delayed: @DelayedHandle` | **не используется** |
| `DelayedHandle` | `uuid: string(uuid)!, status: string!, progress: integer, message: string` | **не используется** |
| `NotModifiedResponse` | — (пустое тело) | `system/manifest` (304) |
| `FileDownloadResponse` | — (пустое тело) | `{slug}/export` (200) |
| `ValidationErrorResponse` | `success: boolean!, payload: @ValidationErrorPayload` | `settings_{slug}/update` (422), `dashboard/reset` (422), `auth/login` (422), `auth/forgotPassword` (422), `auth/resetPassword` (422), `auth/verifyEmail` (422), `delayed/run` (422), `uploads/upload` (422), `uploads/image` (422), `uploads/serve` (422), `profile/update` (422), `profile/changePassword` (422), `profile/twoFactorDisable` (422), `profile/twoFactorRegenerateCodes` (422), `profile/tokenCreate` (422), `{slug}_views/create` (422), `system/setLocale` (422), `system/setTheme` (422), `{screen}/runMethod` (422), `{screen}/listener` (422), `{slug}/listener` (422), `{slug}/create` (422), `{slug}/update` (422), `{slug}/export` (422), `{slug}/action` (422), `{slug}/reorder` (422), `{slug}/replicate` (422), `{slug}/restore` (422), `{slug}/forceDelete` (422), `{slug}/inlineUpdate` (422), `import/upload` (422) |
| `ValidationErrorPayload` | `errorKey: string!, message: string!, messages: object!` | вложенный |
| `UnauthenticatedErrorResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `dashboard/reset` (401), `profile/show` (401), `system/bootstrap` (401) |
| `ForbiddenErrorResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `dashboard/get` (403), `dashboard/savePeriod` (403), `dashboard/save` (403), `dashboard/widgets` (403), `dashboard/reset` (403), `auth/startImpersonation` (403), `delayed/run` (403), `{slug}_views/update` (403), `{slug}_views/delete` (403), `{screen}/runMethod` (403), `{slug}/listener` (403), `{slug}/action` (403) |
| `NotFoundErrorResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `dashboard/get` (404), `dashboard/savePeriod` (404), `dashboard/save` (404), `dashboard/widgets` (404), `dashboard/reset` (404), `auth/startImpersonation` (404), `delayed/status` (404), `uploads/serve` (404), `profile/tokensList` (404), `profile/tokenRevoke` (404), `{slug}_views/update` (404), `{slug}_views/delete` (404), `{screen}/listener` (404), `{slug}/editScreen` (404), `{slug}/listener` (404), `{slug}/viewScreen` (404), `{slug}/read` (404), `{slug}/update` (404), `{slug}/action` (404), `{slug}/replicate` (404), `{slug}/restore` (404), `{slug}/forceDelete` (404), `{slug}/delete` (404), `{slug}/inlineUpdate` (404), `import/status` (404), `notifications/markAsRead` (404), `notifications/destroy` (404) |
| `ThrottledResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/forgotPassword` (429), `auth/resendEmailVerification` (429) |
| `MethodNotAllowedResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `PayloadTooLargeResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `UnsupportedMediaTypeResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `ConflictResponse` | `success: boolean!, payload: @ConflictPayload` | **не используется** |
| `ConflictPayload` | `errorKey: string!, message: string!, current: object` | **не используется** |
| `SimpleErrorPayload` | `errorKey: string!, message: string!` | вложенный |
| `AdminUserSummary` | `id: integer!, name: string!, email: string(email)!, avatar: string, locale: string!, theme: string!, twoFactorEnabled: boolean!, impersonator: @ImpersonatorRef` | вложенный |
| `ImpersonatorRef` | `id: integer!, name: string!` | вложенный |
| `AuditLogEntry` | `id: integer!, user: @AuditUserRef, event: string!, subject_type: string, subject_id: string, attributes: object, old: object, new: object, ip: string, user_agent: string, created_at: string(date-time)!` | вложенный |
| `AuditUserRef` | `id: integer!, name: string!, email: string(email)!` | вложенный |
| `FieldSchema` | `name: string!, type: string!, label: string!, placeholder: string, help: string, required: boolean!, rules: array!, options: object!, visibility: @FieldVisibility, reactive: @FieldReactive, defaultValue: object` | вложенный |
| `FieldVisibility` | `create: boolean!, update: boolean!, view: boolean!` | вложенный |
| `FieldReactive` | `reloadFor: array!, endpoint: string!` | вложенный |
| `ColumnSchema` | `name: string!, label: string!, type: string!, sortable: boolean!, searchable: boolean!, copyable: boolean!, width: string, defaultHidden: boolean!, cantHide: boolean!, align: string!, editable: @ColumnEditable, summary: array, preset: string, meta: object` | вложенный |
| `ColumnEditable` | `field: string!, validation: array!` | вложенный |
| `FilterSchema` | `name: string!, label: string!, type: string!, options: array, default: object, multiple: boolean!` | вложенный |
| `ActionSchema` | `name: string!, label: string!, icon: string, type: string!, confirm: @ActionConfirm, permission: string, primary: boolean!, destructive: boolean!, position: array!, endpoint: string, parameters: @FieldSchema[]` | вложенный |
| `ActionConfirm` | `message: string!, title: string!` | вложенный |
| `LayoutSchema` | `id: string!, type: string!, props: object!, children: @LayoutSchema[]` | вложенный |
| `MenuItem` | `key: string!, label: string!, icon: string, url: string, badge: string, children: @MenuItem[], order: integer!` | вложенный |
| `PermissionGroup` | `name: string!, items: @PermissionItem[]` | вложенный |
| `PermissionItem` | `key: string!, label: string!` | вложенный |
| `PluginManifest` | `id: string!, version: string!, requires: array!` | вложенный |
| `PaginationMeta` | `page: integer!, per_page: integer!, total: integer!, last_page: integer!, from: integer, to: integer` | вложенный |

## AdminApiSystemSchemas (98)

| Шаблон | Поля | Где используется |
|---|---|---|
| `LocaleUpdatedResponse` | `success: boolean!, payload: @LocaleUpdatedPayload` | `system/setLocale` (200) |
| `LocaleUpdatedPayload` | `locale: string!` | вложенный |
| `ThemeStateResponse` | `success: boolean!, payload: @ThemeStatePayload` | `system/theme` (200) |
| `ThemeStatePayload` | `current: string!, default: string!, available: array!` | вложенный |
| `ThemeUpdatedResponse` | `success: boolean!, payload: @ThemeUpdatedPayload` | `system/setTheme` (200) |
| `ThemeUpdatedPayload` | `theme: string!` | вложенный |
| `StatusResponse` | `success: boolean!, payload: @StatusPayload` | `system/status` (200) |
| `StatusPayload` | `indicators: @StatusIndicatorState[]` | вложенный |
| `StatusIndicatorState` | `key: string!, status: string!, label: string!, detail: string, url: string` | вложенный |
| `SettingsReadResponse` | `success: boolean!, payload: @SettingsReadPayload` | `settings_{slug}/read` (200) |
| `SettingsReadPayload` | `values: object!` | вложенный |
| `SettingsUpdatedResponse` | `success: boolean!, payload: @SettingsUpdatedPayload` | `settings_{slug}/update` (200) |
| `SettingsUpdatedPayload` | `values: object!, message: string!` | вложенный |
| `AuditTimelineResponse` | `success: boolean!, payload: @AuditTimelinePayload` | `audit/timeline` (200) |
| `AuditTimelinePayload` | `data: array!` | вложенный |
| `DelayedProcessRunResponse` | `success: boolean!, payload: @DelayedProcessRunPayload` | `delayed/run` (200) |
| `DelayedProcessRunPayload` | `uuid: string(uuid)!, status: string!` | вложенный |
| `DelayedProcessStatusResponse` | `success: boolean!, payload: @DelayedProcessStatus` | `delayed/status` (200) |
| `UploadResponse` | `success: boolean!, payload: @UploadPayload` | `uploads/upload` (200), `uploads/image` (200) |
| `UploadPayload` | `disk: string, path: string, url: string, name: string, size: integer!, mime: string` | вложенный |
| `ImportStartResponse` | `success: boolean!, payload: @ImportProcessPayload` | `import/start` (200) |
| `ImportStatusResponse` | `success: boolean!, payload: @ImportProcessPayload` | `import/status` (200) |
| `ImportProcessPayload` | `process: object!` | вложенный |
| `NotificationListResponse` | `success: boolean!, payload: @NotificationListPayload` | `notifications/list` (200) |
| `NotificationListPayload` | `data: @AdminNotification[], meta: @PaginationMeta` | вложенный |
| `NotificationUnreadResponse` | `success: boolean!, payload: @NotificationUnreadPayload` | `notifications/unread` (200) |
| `NotificationUnreadPayload` | `count: integer!, data: @AdminNotification[]` | вложенный |
| `NotificationMarkResponse` | `success: boolean!, payload: @NotificationMarkPayload` | `notifications/markAsRead` (200) |
| `NotificationMarkPayload` | `id: string(uuid)!, unread_count: integer!` | вложенный |
| `NotificationMarkAllResponse` | `success: boolean!, payload: @NotificationMarkAllPayload` | `notifications/markAllAsRead` (200) |
| `NotificationMarkAllPayload` | `updated: integer!, unread_count: integer!` | вложенный |
| `SavedViewListResponse` | `success: boolean!, payload: @SavedViewListPayload` | `{slug}_views/list` (200) |
| `SavedViewListPayload` | `data: @SavedViewListItem[]` | вложенный |
| `SavedViewListItem` | `id: integer!, name: string!, state: object!, is_default: boolean!, owned: boolean!` | вложенный |
| `DashboardLayoutResponse` | `success: boolean!, payload: @DashboardLayoutPayload` | `dashboard/get` (200) |
| `DashboardLayoutPayload` | `layout: array, period: string` | вложенный |
| `DashboardLayoutSavedResponse` | `success: boolean!, payload: @DashboardLayoutSavedPayload` | `dashboard/save` (200) |
| `DashboardLayoutSavedPayload` | `id: integer!, widgets: @WidgetLayoutItem[]` | вложенный |
| `GlobalSearchResponse` | `success: boolean!, payload: @GlobalSearchPayload` | `system/search` (200) |
| `GlobalSearchPayload` | `query: string!, groups: @GlobalSearchGroup[]!` | вложенный |
| `GlobalSearchGroup` | `slug: string!, label: string!, icon: string, items: @GlobalSearchItem[]!, hasMore: boolean!, moreUrl: string!` | вложенный |
| `GlobalSearchItem` | `id: string!, title: string!, subtitle: string, url: string!` | вложенный |
| `DashboardLayoutResetResponse` | `success: boolean!, payload: @DashboardLayoutResetPayload` | `dashboard/reset` (200) |
| `DashboardLayoutResetPayload` | `key: string!` | вложенный |
| `DashboardWidgetsResponse` | `success: boolean!, payload: @DashboardWidgetsPayload` | `dashboard/widgets` (200) |
| `DashboardWidgetsPayload` | `widgets: @WidgetInstance[], period: string!` | вложенный |
| `BootstrapResponse` | `success: boolean!, payload: @BootstrapPayload` | `system/bootstrap` (200) |
| `BootstrapPayload` | `csrf: string!, baseUrl: string!, apiUrl: string!, locale: string!, availableLocales: array!, theme: string!, brand: @BrandConfig, user: @AdminUserSummary, permissions: array!, manifestVersion: string, pluginVersions: object!, config: object!` | вложенный |
| `BrandConfig` | `name: string!, logo: string, favicon: string` | вложенный |
| `ManifestResponse` | `success: boolean!, payload: @ManifestPayload` | `system/manifest` (200) |
| `ManifestPayload` | `version: string!, locale: string!, resources: array!, screens: array!, settings: array!, dashboards: array!, plugins: @PluginManifest[], permissions: @PermissionGroup[]` | вложенный |
| `AdminUserSummaryResponse` | `success: boolean!, payload: @AdminUserSummary` | `system/me` (200) |
| `MenuResponse` | `success: boolean!, payload: @MenuPayload` | `system/menu` (200) |
| `MenuPayload` | `items: @MenuItem[]` | вложенный |
| `LocalesResponse` | `success: boolean!, payload: @LocalesPayload` | `system/locales` (200) |
| `LocalesPayload` | `available: array!, current: string!, fallback: string!` | вложенный |
| `PermissionsResponse` | `success: boolean!, payload: @PermissionsPayload` | `system/permissions` (200) |
| `PermissionsPayload` | `groups: @PermissionGroup[]` | вложенный |
| `PluginsResponse` | `success: boolean!, payload: @PluginsPayload` | `system/status` (200) |
| `PluginsPayload` | `plugins: @PluginManifest[]` | вложенный |
| `NotificationsListResponse` | `success: boolean!, payload: @NotificationsListPayload` | **не используется** |
| `NotificationsListPayload` | `data: @AdminNotification[], meta: @PaginationMeta, unread_count: integer!` | **не используется** |
| `AdminNotification` | `id: string(uuid)!, type: string!, data: @AdminNotificationData, read_at: string(date-time), created_at: string(date-time)!` | вложенный |
| `AdminNotificationData` | `title: string!, message: string!, icon: string, color: string, action_url: string, action_label: string` | вложенный |
| `NotificationItemResponse` | `success: boolean!, payload: @AdminNotification` | **не используется** |
| `AuditListResponse` | `success: boolean!, payload: @AuditListPayload` | `audit/list` (200) |
| `AuditListPayload` | `data: @AuditLogEntry[], meta: @PaginationMeta` | вложенный |
| `LoginResponse` | `success: boolean!, payload: @LoginPayload` | `auth/login` (200), `auth/twoFactorChallenge` (200), `auth/resetPassword` (200), `auth/stopImpersonation` (200) |
| `LoginPayload` | `user: @AdminUserSummary, redirect_url: string!` | вложенный |
| `TwoFactorRequiredResponse` | `success: boolean!, payload: @TwoFactorRequiredPayload` | **не используется** |
| `TwoFactorRequiredPayload` | `errorKey: string!, message: string!, challenge_token: string!` | **не используется** |
| `InvalidCredentialsResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/login` (401) |
| `AccountInactiveResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/login` (403) |
| `InvalidTwoFactorResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/twoFactorChallenge` (401), `profile/twoFactorConfirm` (422) |
| `InvalidRecoveryCodeResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/twoFactorRecovery` (401) |
| `RecoveryLoginResponse` | `success: boolean!, payload: @RecoveryLoginPayload` | `auth/twoFactorRecovery` (200) |
| `RecoveryLoginPayload` | `user: @AdminUserSummary, redirect_url: string!, recovery_codes_remaining: integer!` | вложенный |
| `ImpersonationResponse` | `success: boolean!, payload: @ImpersonationPayload` | `auth/startImpersonation` (200) |
| `ImpersonationPayload` | `user: @AdminUserSummary, impersonator: @ImpersonatorRef, redirect_url: string!` | вложенный |
| `NoActiveImpersonationResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `auth/stopImpersonation` (400) |
| `ProfileResponse` | `success: boolean!, payload: @ProfilePayload` | `profile/show` (200) |
| `ProfilePayload` | `user: @AdminUserSummary, available_locales: array!, available_themes: array!, two_factor: @ProfileTwoFactor, api_tokens_enabled: boolean!` | вложенный |
| `ProfileTwoFactor` | `enabled: boolean!, confirmed_at: string(date-time), recovery_codes_remaining: integer!` | вложенный |
| `ProfileUpdateResponse` | `success: boolean!, payload: @ProfileUpdatePayload` | `profile/update` (200) |
| `ProfileUpdatePayload` | `user: @AdminUserSummary` | вложенный |
| `TwoFactorStatusResponse` | `success: boolean!, payload: @TwoFactorStatusPayload` | `profile/twoFactorStatus` (200) |
| `TwoFactorStatusPayload` | `enabled: boolean!, confirmed_at: string(date-time), qr_code_svg: string, secret: string, qr_uri: string, recovery_codes: array` | вложенный |
| `TwoFactorSetupResponse` | `success: boolean!, payload: @TwoFactorSetupPayload` | `profile/twoFactorEnable` (200) |
| `TwoFactorSetupPayload` | `qr_code_svg: string, secret: string!, qr_uri: string!, recovery_codes: array!` | вложенный |
| `TwoFactorConfirmedResponse` | `success: boolean!, payload: @TwoFactorConfirmedPayload` | `profile/twoFactorConfirm` (200) |
| `TwoFactorConfirmedPayload` | `enabled: boolean!, confirmed_at: string(date-time)!` | вложенный |
| `RecoveryCodesResponse` | `success: boolean!, payload: @RecoveryCodesPayload` | `profile/twoFactorRegenerateCodes` (200) |
| `RecoveryCodesPayload` | `recovery_codes: array!` | вложенный |
| `ApiTokenListResponse` | `success: boolean!, payload: @ApiTokenListPayload` | `profile/tokensList` (200) |
| `ApiTokenListPayload` | `data: @ApiToken[]` | вложенный |
| `ApiToken` | `id: integer!, name: string!, abilities: array!, last_used_at: string(date-time), created_at: string(date-time)!, expires_at: string(date-time)` | вложенный |
| `ApiTokenCreatedResponse` | `success: boolean!, payload: @ApiTokenCreatedPayload` | `profile/tokenCreate` (200) |
| `ApiTokenCreatedPayload` | `token: @ApiToken, plain_text_token: string!` | вложенный |

## AdminApiResourceSchemas (71)

| Шаблон | Поля | Где используется |
|---|---|---|
| `ResourceListScreenResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{slug}/listScreen` (200) |
| `ResourceTreeScreenResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{slug}/treeScreen` (200) |
| `ResourceCreateScreenResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{slug}/createScreen` (200) |
| `ResourceEditScreenResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{slug}/editScreen` (200) |
| `ResourceViewScreenResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{slug}/viewScreen` (200) |
| `ResourceTreeResponse` | `success: boolean!, payload: @ResourceTreePayload` | `{slug}/tree` (200) |
| `ResourceTreePayload` | `data: array!, meta: @ResourceTreeMeta` | вложенный |
| `ResourceTreeMeta` | `total: integer!, max_depth: integer!, parent_key: string!, label_column: string!` | вложенный |
| `ResourceReorderedResponse` | `success: boolean!, payload: @ResourceReorderedPayload` | `{slug}/reorder` (200) |
| `ResourceReorderedPayload` | `count: integer!, message: string!` | вложенный |
| `ResourceReplicatedResponse` | `success: boolean!, payload: @ResourceReplicatedPayload` | `{slug}/replicate` (200) |
| `ResourceReplicatedPayload` | `record: object!, redirect_url: string!, message: string!` | вложенный |
| `ResourceForceDeletedResponse` | `success: boolean!, payload: @ResourceForceDeletedPayload` | `{slug}/forceDelete` (200) |
| `ResourceForceDeletedPayload` | `id: mixed!, message: string!` | вложенный |
| `ResourceInlineUpdatedResponse` | `success: boolean!, payload: @ResourceInlineUpdatedPayload` | `{slug}/inlineUpdate` (200) |
| `ResourceInlineUpdatedPayload` | `record: object!, column: string!, value: mixed!` | вложенный |
| `ResourceSummaryResponse` | `success: boolean!, payload: @ResourceSummaryPayload` | `{slug}/summary` (200) |
| `ResourceSummaryPayload` | `summary: object!` | вложенный |
| `ConflictErrorResponse` | `success: boolean!, payload: @SimpleErrorPayload` | `{slug}/tree` (409) |
| `ResourceMetaResponse` | `success: boolean!, payload: @ResourceMetaPayload` | `{slug}/meta` (200) |
| `ResourceMetaPayload` | `fields: @FieldSchema[], columns: @ColumnSchema[], filters: @FilterSchema[], actions: @ActionSchema[], permissions: object!, features: @ResourceFeatures` | вложенный |
| `ResourceFeatures` | `softDeletes: boolean!, replicable: boolean!, reorderable: object, importable: boolean!, exportable: array!, polling: string, warnOnUnsavedChanges: boolean!` | вложенный |
| `ResourceSearchResponse` | `success: boolean!, payload: @ResourceSearchPayload` | `{slug}/search` (200) |
| `ResourceSearchPayload` | `data: array!, meta: @ResourceSearchMeta` | вложенный |
| `ResourceSearchMeta` | `page: integer!, per_page: integer!, total: integer!, last_page: integer!, from: integer, to: integer, summary: object, groups: array` | вложенный |
| `ResourceReadResponse` | `success: boolean!, payload: @ResourceReadPayload` | `{slug}/read` (200) |
| `ResourceReadPayload` | `record: object!, state: object!, permissions: @ResourceRecordPermissions, audit_summary: @ResourceAuditSummary, etag: string!` | вложенный |
| `ResourceRecordPermissions` | `update: boolean!, delete: boolean!, force_delete: boolean!, restore: boolean!, replicate: boolean!` | вложенный |
| `ResourceAuditSummary` | `created_by: @AuditUserRef, created_at: string(date-time)!, updated_by: @AuditUserRef, updated_at: string(date-time)!, deleted_at: string(date-time), audit_count: integer!` | вложенный |
| `ResourceCreatedResponse` | `success: boolean!, payload: @ResourceCreatedPayload` | `{slug}/create` (201) |
| `ResourceCreatedPayload` | `record: object!, redirect_url: string!, message: string!` | вложенный |
| `ResourceUpdatedResponse` | `success: boolean!, payload: @ResourceUpdatedPayload` | `{slug}/update` (200) |
| `ResourceUpdatedPayload` | `record: object!, state: object!, etag: string!, message: string!` | вложенный |
| `ResourceDeletedResponse` | `success: boolean!, payload: @ResourceDeletedPayload` | `{slug}/delete` (200) |
| `ResourceDeletedPayload` | `record: object, message: string!` | вложенный |
| `ResourceRestoredResponse` | `success: boolean!, payload: @ResourceRestoredPayload` | `{slug}/restore` (200) |
| `ResourceRestoredPayload` | `record: object!, message: string!` | вложенный |
| `InlineEditResponse` | `success: boolean!, payload: @InlineEditPayload` | **не используется** |
| `InlineEditPayload` | `record: object!, message: string` | **не используется** |
| `InfolistResponse` | `success: boolean!, payload: @InfolistPayload` | **не используется** |
| `InfolistPayload` | `record: object!, layout: @LayoutSchema[], etag: string!` | **не используется** |
| `ReactiveFieldResponse` | `success: boolean!, payload: @ReactiveFieldPayload` | **не используется** |
| `ReactiveFieldPayload` | `field: string!, options: array, value: object, visible: boolean, rules: array` | **не используется** |
| `RelationAttachedResponse` | `success: boolean!, payload: @RelationAttachedPayload` | **не используется** |
| `RelationAttachedPayload` | `related: object!, message: string!` | **не используется** |
| `RelationSyncResponse` | `success: boolean!, payload: @RelationSyncPayload` | **не используется** |
| `RelationSyncPayload` | `attached: integer!, detached: integer!` | **не используется** |
| `SavedViewsListResponse` | `success: boolean!, payload: @SavedViewsListPayload` | **не используется** |
| `SavedViewsListPayload` | `data: @SavedView[]` | **не используется** |
| `SavedView` | `id: integer!, name: string!, payload: @SavedViewPayloadData, is_shared: boolean!, is_default: boolean!, owner: @AuditUserRef, created_at: string(date-time)!` | вложенный |
| `SavedViewPayloadData` | `filter: object!, sort: string, columns: array, group_by: string, per_page: integer` | вложенный |
| `SavedViewResponse` | `success: boolean!, payload: @SavedViewPayloadWrapper` | `{slug}_views/create` (200), `{slug}_views/update` (200) |
| `SavedViewPayloadWrapper` | `view: @SavedView` | вложенный |
| `TablePreferencesResponse` | `success: boolean!, payload: @TablePreferencesPayload` | **не используется** |
| `TablePreferencesPayload` | `preferences: @TablePreferences` | **не используется** |
| `TablePreferences` | `columns: @TablePreferencesColumn[], per_page: integer` | **не используется** |
| `TablePreferencesColumn` | `name: string!, visible: boolean!, order: integer!` | **не используется** |
| `BulkActionResponse` | `success: boolean!, payload: @BulkActionPayload` | **не используется** |
| `BulkActionPayload` | `affected: integer!, message: string!, refresh: boolean!, failed: @BulkActionFailedItem[]` | **не используется** |
| `BulkActionFailedItem` | `id: string!, error: string!` | **не используется** |
| `SingleActionResponse` | `success: boolean!, payload: @SingleActionPayload` | **не используется** |
| `SingleActionPayload` | `record: object, message: string!, redirect_url: string, refresh: boolean!, download_url: string` | **не используется** |
| `ActionParametersResponse` | `success: boolean!, payload: @ActionParametersPayload` | **не используется** |
| `ActionParametersPayload` | `title: string!, description: string, fields: @FieldSchema[], submit_label: string!, cancel_label: string!, confirm: @ActionConfirm` | **не используется** |
| `SettingsMetaResponse` | `success: boolean!, payload: @SettingsMetaPayload` | `settings_{slug}/meta` (200) |
| `SettingsMetaPayload` | `fields: @FieldSchema[], layout: @LayoutSchema[], permissions: @SettingsPermissions` | вложенный |
| `SettingsPermissions` | `update: boolean!` | вложенный |
| `SettingsShowResponse` | `success: boolean!, payload: @SettingsShowPayload` | **не используется** |
| `SettingsShowPayload` | `state: object!, layout: @LayoutSchema[], fields: @FieldSchema[], permissions: @SettingsPermissions, etag: string!` | **не используется** |
| `SettingsUpdateResponse` | `success: boolean!, payload: @SettingsUpdatePayload` | **не используется** |
| `SettingsUpdatePayload` | `state: object!, etag: string!, message: string!, affected_keys: array!` | **не используется** |

## AdminApiUiSchemas (50)

| Шаблон | Поля | Где используется |
|---|---|---|
| `ScreenStateResponse` | `success: boolean!, payload: @ScreenStatePayload` | `{screen}/state` (200) |
| `ScreenStatePayload` | `state: object!, name: string!, description: string, layout: @LayoutSchema[], command_bar: @ActionSchema[], permissions: array!, etag: string!` | вложенный |
| `ScreenMethodResponse` | `success: boolean!, payload: @ScreenMethodPayload` | `{screen}/runMethod` (200) |
| `ScreenMethodPayload` | `state: object!, layouts: object, alerts: @ScreenAlert[], redirect_url: string, refresh: boolean!, download_url: string, message: string!` | вложенный |
| `ScreenAlert` | `type: string!, message: string!, title: string, duration_ms: integer` | вложенный |
| `ListenerResponse` | `success: boolean!, payload: @ListenerPayload` | `{screen}/listener` (200), `{slug}/listener` (200) |
| `ListenerPayload` | `listener: string!, state: object!, layouts: @LayoutSchema[]` | вложенный |
| `ScreenAsyncResponse` | `success: boolean!, payload: @ScreenAsyncPayload` | **не используется** |
| `ScreenAsyncPayload` | `layouts: object!, state_patch: object` | **не используется** |
| `DashboardsListResponse` | `success: boolean!, payload: @DashboardsListPayload` | **не используется** |
| `DashboardsListPayload` | `data: @DashboardSummary[], default: string` | **не используется** |
| `DashboardSummary` | `slug: string!, title: string!, description: string, icon: string, url: string!, permission: string, is_customizable: boolean!` | **не используется** |
| `DashboardShowResponse` | `success: boolean!, payload: @DashboardShowPayload` | **не используется** |
| `DashboardShowPayload` | `dashboard: @DashboardSummary, widgets: @WidgetInstance[], layout: @WidgetLayoutItem[], user_layout_saved_at: string(date-time)` | **не используется** |
| `WidgetInstance` | `id: string!, type: string!, label: string!, description: string, url: string!, poll: string, permission: string, initial_data: object, options: object!` | вложенный |
| `WidgetLayoutItem` | `widget_id: string!, x: integer!, y: integer!, w: integer!, h: integer!` | вложенный |
| `WidgetDataResponse` | `success: boolean!, payload: @WidgetDataPayload` | **не используется** |
| `WidgetDataPayload` | `data: object!, fetched_at: string(date-time)!, next_refresh_at: string(date-time)` | **не используется** |
| `LayoutSavedResponse` | `success: boolean!, payload: @LayoutSavedPayload` | **не используется** |
| `LayoutSavedPayload` | `saved_at: string(date-time)!` | **не используется** |
| `DashboardCreatedResponse` | `success: boolean!, payload: @DashboardCreatedPayload` | **не используется** |
| `DashboardCreatedPayload` | `dashboard: @DashboardSummary, redirect_url: string!` | **не используется** |
| `UploadCreatedResponse` | `success: boolean!, payload: @UploadCreatedPayload` | **не используется** |
| `UploadCreatedPayload` | `upload: @AdminUpload` | **не используется** |
| `AdminUpload` | `id: string(uuid)!, url: string!, preview_url: string, mime: string!, size: integer!, original_name: string!, width: integer, height: integer, collection: string, created_at: string(date-time)!` | **не используется** |
| `UploadShowResponse` | `success: boolean!, payload: @UploadCreatedPayload` | **не используется** |
| `ChunkedStartResponse` | `success: boolean!, payload: @ChunkedStartPayload` | **не используется** |
| `ChunkedStartPayload` | `upload_id: string(uuid)!, chunk_endpoint: string!, finish_endpoint: string!, expires_at: string(date-time)!` | **не используется** |
| `ChunkAcceptedResponse` | `success: boolean!, payload: @ChunkAcceptedPayload` | **не используется** |
| `ChunkAcceptedPayload` | `received: integer!, total: integer!, next_index: integer` | **не используется** |
| `ChunkChecksumMismatchResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `DelayedStatusResponse` | `success: boolean!, payload: @DelayedStatusPayload` | **не используется** |
| `DelayedStatusPayload` | `processes: @DelayedProcessStatus[]` | **не используется** |
| `DelayedProcessStatus` | `uuid: string(uuid)!, status: string!, progress: integer!, message: string, started_at: string(date-time), finished_at: string(date-time), duration_ms: integer, attempts: integer!, data: object, error: @DelayedProcessError` | вложенный |
| `DelayedProcessError` | `class: string!, message: string!` | вложенный |
| `DelayedCancelResponse` | `success: boolean!, payload: @DelayedCancelPayload` | **не используется** |
| `DelayedCancelPayload` | `status: string!` | **не используется** |
| `DelayedListResponse` | `success: boolean!, payload: @DelayedListPayload` | **не используется** |
| `DelayedListPayload` | `data: @DelayedProcessStatus[], meta: @PaginationMeta` | **не используется** |
| `CannotCancelResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `MissingExportDriverResponse` | `success: boolean!, payload: @MissingExportDriverPayload` | **не используется** |
| `MissingExportDriverPayload` | `errorKey: string!, message: string!, command: string` | **не используется** |
| `InvalidImportFileResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `ImportUploadResponse` | `success: boolean!, payload: @ImportUploadPayload` | `import/upload` (200) |
| `ImportUploadPayload` | `upload_id: string(uuid)!, columns_detected: array!, sample_rows: array!, total_rows_estimate: integer!, target_fields: @ImportTargetField[], auto_mapping: object!` | вложенный |
| `ImportTargetField` | `name: string!, label: string!, required: boolean!` | вложенный |
| `ImportPreviewResponse` | `success: boolean!, payload: @ImportPreviewPayload` | `import/preview` (200) |
| `ImportPreviewPayload` | `preview: @ImportPreviewRow[], summary: @ImportPreviewSummary` | вложенный |
| `ImportPreviewRow` | `row_number: integer!, status: string!, data: object!, errors: object` | вложенный |
| `ImportPreviewSummary` | `total: integer!, will_create: integer!, will_update: integer!, will_skip: integer!, will_fail: integer!` | вложенный |

## AdminApiSisterPackSchemas (16)

| Шаблон | Поля | Где используется |
|---|---|---|
| `SearchResponse` | `success: boolean!, payload: @SearchPayload` | **не используется** |
| `SearchPayload` | `query: string!, groups: @SearchGroup[], total: integer!, elapsed_ms: integer!` | **не используется** |
| `SearchGroup` | `resource: string!, label: string!, icon: string, count: integer!, has_more: boolean!, more_url: string, items: @SearchItem[]` | **не используется** |
| `SearchItem` | `id: string!, title: string!, subtitle: string, icon: string, url: string!, meta: object, score: number` | **не используется** |
| `SearchUnavailableResponse` | `success: boolean!, payload: @SimpleErrorPayload` | **не используется** |
| `HealthSummaryResponse` | `success: boolean!, payload: @HealthSummaryPayload` | **не используется** |
| `HealthSummaryPayload` | `overall: string!, counts: @HealthCounts, last_run_at: string(date-time)!, failing_checks: @HealthFailingItem[]` | **не используется** |
| `HealthCounts` | `ok: integer!, warning: integer!, failing: integer!` | **не используется** |
| `HealthFailingItem` | `id: string!, name: string!, message: string!` | **не используется** |
| `HealthChecksResponse` | `success: boolean!, payload: @HealthChecksPayload` | **не используется** |
| `HealthChecksPayload` | `checks: @HealthCheckStatus[], last_run_at: string(date-time)!` | **не используется** |
| `HealthCheckStatus` | `id: string!, name: string!, category: string!, status: string!, message: string, meta: object!, frequency: string!, last_run_at: string(date-time)!, duration_ms: integer!` | **не используется** |
| `HealthCheckStatusResponse` | `success: boolean!, payload: @HealthCheckStatus` | **не используется** |
| `HealthHistoryResponse` | `success: boolean!, payload: @HealthHistoryPayload` | **не используется** |
| `HealthHistoryPayload` | `data: @HealthHistoryItem[], meta: @PaginationMeta` | **не используется** |
| `HealthHistoryItem` | `ran_at: string(date-time)!, status: string!, duration_ms: integer!, message: string` | **не используется** |
