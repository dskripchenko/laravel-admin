# API: Schema-templates

Полный реестр всех named-templates, на которые ссылаются `@response` теги в action-docblock'ах. Объявление — в `src/Http/AdminApi.php` (через `getOpenApiTemplates()` + traits в `src/Http/Schemas/`).

> Объяснение механизма (`$useResponseTemplates`, type-spec syntax) — в [registration.md §4](registration.md). Применение в action'ах — в файлах конкретных контроллеров ([system.md](system.md), [auth.md](auth.md), [resources.md](resources.md), ...).

Обозначения в столбце «Структура»:

- `string!` — обязательное поле.
- `string` — опциональное (может быть `null` или отсутствовать).
- `string(format)` — с OpenAPI-форматом.
- `@Ref` — ссылка на другую схему.
- `@Ref[]` — массив ссылок.

---

## Common (envelope, errors, building blocks)

### Envelope

| Template | Структура | Когда |
|---|---|---|
| `SuccessResponse` | `{ success: bool!, payload: object|null }` | 200 OK с пустым/null payload |
| `AffectedResponse` | `{ success: bool!, payload: { affected: int! } }` | bulk-операции, mark-all-read |
| `GenericMessageResponse` | `{ success: bool!, payload: { message: str! } }` | forgot-password, resend-email-verification |
| `DelayedResponse` | `{ success: bool!, payload: { delayed: @DelayedHandle } }` | 202 Accepted, фоновая операция |
| `NotModifiedResponse` | пустое тело | 304 Not Modified (etag match) |
| `FileDownloadResponse` | бинарный файл, `Content-Disposition: attachment` | export download, errors-csv |

### Errors

| Template | Структура | HTTP |
|---|---|---|
| `ValidationErrorResponse` | `{ success: false, payload: { errorKey: 'validation', message: str!, messages: object! } }` | 422 |
| `UnauthenticatedErrorResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 401 |
| `ForbiddenErrorResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 403 |
| `NotFoundErrorResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 404 |
| `ThrottledResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 429 |
| `MethodNotAllowedResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 405 |
| `PayloadTooLargeResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 413 |
| `UnsupportedMediaTypeResponse` | `{ success: false, payload: @SimpleErrorPayload }` | 415 |
| `ConflictResponse` | `{ success: false, payload: { errorKey: 'conflict', message: str!, current: object } }` | 409 |
| `SimpleErrorPayload` | `{ errorKey: str!, message: str! }` | building block |

### Building blocks

| Template | Поля |
|---|---|
| `AdminUserSummary` | `id!, name!, email(email)!, avatar, locale!, theme!, twoFactorEnabled!, impersonator: @ImpersonatorRef` |
| `ImpersonatorRef` | `id!, name!` |
| `AuditLogEntry` | `id!, user: @AuditUserRef, event!, subject_type, subject_id, attributes, old, new, ip, user_agent, created_at(date-time)!` |
| `AuditUserRef` | `id!, name!, email(email)!` |
| `FieldSchema` | `name!, type!, label!, placeholder, help, required!, rules!, options!, visibility: @FieldVisibility, reactive: @FieldReactive, defaultValue` |
| `FieldVisibility` | `create!, update!, view!` |
| `FieldReactive` | `reloadFor!, endpoint!` |
| `ColumnSchema` | `name!, label!, type!, sortable!, searchable!, copyable!, width, defaultHidden!, cantHide!, align!, editable: @ColumnEditable, summary, preset, meta` |
| `ColumnEditable` | `field!, validation!` |
| `FilterSchema` | `name!, label!, type!, options, default, multiple!` |
| `ActionSchema` | `name!, label!, icon, type!, confirm: @ActionConfirm, permission, primary!, destructive!, position!, endpoint, parameters: @FieldSchema[]` |
| `ActionConfirm` | `message!, title!` |
| `LayoutSchema` | `id!, type!, props!, children: @LayoutSchema[]` (рекурсивная) |
| `MenuItem` | `key!, label!, icon, url, badge, children: @MenuItem[], order!` |
| `PermissionGroup` | `name!, items: @PermissionItem[]` |
| `PermissionItem` | `key!, label!` |
| `PluginManifest` | `id!, version!, requires!` |
| `PaginationMeta` | `page!, per_page!, total!, last_page!, from, to` |
| `DelayedHandle` | `uuid(uuid)!, status!, progress, message` |

---

## System

| Template | Описание |
|---|---|
| `BootstrapResponse` | Полный bootstrap SPA. payload = `@BootstrapPayload`. |
| `BootstrapPayload` | `csrf!, baseUrl!, apiUrl!, locale!, availableLocales!, theme!, brand: @BrandConfig, user: @AdminUserSummary, permissions!, manifestVersion, pluginVersions!, config!` |
| `BrandConfig` | `name!, logo, favicon` |
| `ManifestResponse` | `success!, payload: @ManifestPayload` |
| `ManifestPayload` | `version!, locale!, resources!, screens!, settings!, dashboards!, plugins: @PluginManifest[], permissions: @PermissionGroup[]` |
| `AdminUserSummaryResponse` | `success!, payload: @AdminUserSummary` |
| `MenuResponse` | `success!, payload: { items: @MenuItem[] }` |
| `LocalesResponse` | `success!, payload: { available!, current!, fallback! }` |
| `PermissionsResponse` | `success!, payload: { groups: @PermissionGroup[] }` |
| `PluginsResponse` | `success!, payload: { plugins: @PluginManifest[] }` |
| `NotificationsListResponse` | `success!, payload: { data: @AdminNotification[], meta: @PaginationMeta, unread_count! }` |
| `AdminNotification` | `id(uuid)!, type!, data: @AdminNotificationData, read_at(date-time), created_at(date-time)!` |
| `AdminNotificationData` | `title!, message!, icon, color, action_url, action_label` |
| `NotificationItemResponse` | `success!, payload: @AdminNotification` |
| `AuditListResponse` | `success!, payload: { data: @AuditLogEntry[], meta: @PaginationMeta }` |

---

## Auth

| Template | Описание |
|---|---|
| `LoginResponse` | `success!, payload: { user: @AdminUserSummary, redirect_url! }` |
| `TwoFactorRequiredResponse` | `success!, payload: { errorKey: 'two_factor_required', message!, challenge_token! }` |
| `InvalidCredentialsResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`invalid_credentials`) |
| `InvalidTwoFactorResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`invalid_two_factor_code` или `challenge_expired`) |
| `InvalidRecoveryCodeResponse` | `success!, payload: @SimpleErrorPayload` |
| `RecoveryLoginResponse` | `success!, payload: { user, redirect_url!, recovery_codes_remaining! }` |
| `ImpersonationResponse` | `success!, payload: { user, impersonator: @ImpersonatorRef, redirect_url! }` |
| `NoActiveImpersonationResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`no_active_impersonation`) |

---

## Profile

| Template | Описание |
|---|---|
| `ProfileResponse` | `success!, payload: { user, available_locales!, available_themes!, two_factor: @ProfileTwoFactor, api_tokens_enabled! }` |
| `ProfileTwoFactor` | `enabled!, confirmed_at(date-time), recovery_codes_remaining!` |
| `ProfileUpdateResponse` | `success!, payload: { user }` |
| `TwoFactorStatusResponse` | `success!, payload: { enabled!, confirmed_at, qr_code_svg, secret, qr_uri, recovery_codes }` |
| `TwoFactorSetupResponse` | `success!, payload: { qr_code_svg!, secret!, qr_uri!, recovery_codes! }` |
| `TwoFactorConfirmedResponse` | `success!, payload: { enabled!, confirmed_at(date-time)! }` |
| `RecoveryCodesResponse` | `success!, payload: { recovery_codes! }` |
| `ApiTokenListResponse` | `success!, payload: { data: @ApiToken[] }` |
| `ApiToken` | `id!, name!, abilities!, last_used_at, created_at!, expires_at` |
| `ApiTokenCreatedResponse` | `success!, payload: { token: @ApiToken, plain_text_token! }` |

---

## Resources & Actions

| Template | Описание |
|---|---|
| `ResourceMetaResponse` | `success!, payload: { fields: @FieldSchema[], columns: @ColumnSchema[], filters: @FilterSchema[], actions: @ActionSchema[], permissions, features: @ResourceFeatures }` |
| `ResourceFeatures` | `softDeletes!, replicable!, reorderable, importable!, exportable!, polling, warnOnUnsavedChanges!` |
| `ResourceSearchResponse` | `success!, payload: { data!, meta: @ResourceSearchMeta }` |
| `ResourceSearchMeta` | `page!, per_page!, total!, last_page!, from, to, summary, groups` |
| `ResourceReadResponse` | `success!, payload: { record!, state!, permissions: @ResourceRecordPermissions, audit_summary: @ResourceAuditSummary, etag! }` |
| `ResourceRecordPermissions` | `update!, delete!, force_delete!, restore!, replicate!` |
| `ResourceAuditSummary` | `created_by, created_at!, updated_by, updated_at!, deleted_at, audit_count!` |
| `ResourceCreatedResponse` | `success!, payload: { record!, redirect_url!, message! }` |
| `ResourceUpdatedResponse` | `success!, payload: { record!, state!, etag!, message! }` |
| `ResourceDeletedResponse` | `success!, payload: { record, message! }` |
| `ResourceRestoredResponse` | `success!, payload: { record!, message! }` |
| `InlineEditResponse` | `success!, payload: { record!, message }` |
| `InfolistResponse` | `success!, payload: { record!, layout: @LayoutSchema[], etag! }` |
| `ReactiveFieldResponse` | `success!, payload: { field!, options, value, visible, rules }` |
| `RelationAttachedResponse` | `success!, payload: { related!, message! }` |
| `RelationSyncResponse` | `success!, payload: { attached!, detached! }` |
| `SavedViewsListResponse` | `success!, payload: { data: @SavedView[] }` |
| `SavedView` | `id!, name!, payload: @SavedViewPayloadData, is_shared!, is_default!, owner, created_at!` |
| `SavedViewPayloadData` | `filter!, sort, columns, group_by, per_page` |
| `SavedViewResponse` | `success!, payload: { view: @SavedView }` |
| `TablePreferencesResponse` | `success!, payload: { preferences: @TablePreferences }` |
| `TablePreferences` | `columns: @TablePreferencesColumn[], per_page` |
| `TablePreferencesColumn` | `name!, visible!, order!` |
| `BulkActionResponse` | `success!, payload: { affected!, message!, refresh!, failed: @BulkActionFailedItem[] }` |
| `BulkActionFailedItem` | `id!, error!` |
| `SingleActionResponse` | `success!, payload: { record, message!, redirect_url, refresh!, download_url }` |
| `ActionParametersResponse` | `success!, payload: { title!, description, fields: @FieldSchema[], submit_label!, cancel_label!, confirm: @ActionConfirm }` |

---

## Settings

| Template | Описание |
|---|---|
| `SettingsMetaResponse` | `success!, payload: { fields: @FieldSchema[], layout: @LayoutSchema[], permissions: @SettingsPermissions }` |
| `SettingsPermissions` | `update!` |
| `SettingsShowResponse` | `success!, payload: { state!, layout, fields, permissions, etag! }` |
| `SettingsUpdateResponse` | `success!, payload: { state!, etag!, message!, affected_keys! }` |

---

## Screens

| Template | Описание |
|---|---|
| `ScreenStateResponse` | `success!, payload: { state!, name!, description, layout: @LayoutSchema[], command_bar: @ActionSchema[], permissions!, etag! }` |
| `ScreenMethodResponse` | `success!, payload: { state!, layouts, alerts: @ScreenAlert[], redirect_url, refresh!, download_url, message! }` |
| `ScreenAlert` | `type!, message!, duration_ms` |
| `ScreenAsyncResponse` | `success!, payload: { layouts!, state_patch }` |

---

## Dashboards & Widgets

| Template | Описание |
|---|---|
| `DashboardsListResponse` | `success!, payload: { data: @DashboardSummary[], default }` |
| `DashboardSummary` | `slug!, title!, description, icon, url!, permission, is_customizable!` |
| `DashboardShowResponse` | `success!, payload: { dashboard, widgets: @WidgetInstance[], layout: @WidgetLayoutItem[], user_layout_saved_at }` |
| `WidgetInstance` | `id!, type!, label!, description, url!, poll, permission, initial_data, options!` |
| `WidgetLayoutItem` | `widget_id!, x!, y!, w!, h!` |
| `WidgetDataResponse` | `success!, payload: { data!, fetched_at!, next_refresh_at }` |
| `LayoutSavedResponse` | `success!, payload: { saved_at(date-time)! }` |
| `DashboardCreatedResponse` | `success!, payload: { dashboard, redirect_url! }` |

---

## Uploads

| Template | Описание |
|---|---|
| `UploadCreatedResponse` | `success!, payload: { upload: @AdminUpload }` |
| `AdminUpload` | `id(uuid)!, url!, preview_url, mime!, size!, original_name!, width, height, collection, created_at(date-time)!` |
| `UploadShowResponse` | `success!, payload: { upload: @AdminUpload }` (тот же shape) |
| `ChunkedStartResponse` | `success!, payload: { upload_id(uuid)!, chunk_endpoint!, finish_endpoint!, expires_at(date-time)! }` |
| `ChunkAcceptedResponse` | `success!, payload: { received!, total!, next_index }` |
| `ChunkChecksumMismatchResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`chunk_checksum_mismatch`) |

---

## Delayed processes

| Template | Описание |
|---|---|
| `DelayedStatusResponse` | `success!, payload: { processes: @DelayedProcessStatus[] }` |
| `DelayedProcessStatus` | `uuid(uuid)!, status!, progress!, message, started_at, finished_at, duration_ms, attempts!, data, error: @DelayedProcessError` |
| `DelayedProcessError` | `class!, message!` |
| `DelayedCancelResponse` | `success!, payload: { status! }` (cancelled\|finishing) |
| `DelayedListResponse` | `success!, payload: { data: @DelayedProcessStatus[], meta: @PaginationMeta }` |
| `CannotCancelResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`cannot_cancel`) |

---

## Exports & Imports

| Template | Описание |
|---|---|
| `MissingExportDriverResponse` | `success!, payload: { errorKey: 'missing_export_driver', message!, command }` |
| `InvalidImportFileResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`invalid_import_file`) |
| `ImportUploadResponse` | `success!, payload: { upload_id(uuid)!, columns_detected!, sample_rows!, total_rows_estimate!, target_fields: @ImportTargetField[], auto_mapping! }` |
| `ImportTargetField` | `name!, label!, required!` |
| `ImportPreviewResponse` | `success!, payload: { preview: @ImportPreviewRow[], summary: @ImportPreviewSummary }` |
| `ImportPreviewRow` | `row_number!, status!, data!, errors` |
| `ImportPreviewSummary` | `total!, will_create!, will_update!, will_skip!, will_fail!` |

---

## Search (sister-pack)

| Template | Описание |
|---|---|
| `SearchResponse` | `success!, payload: { query!, groups: @SearchGroup[], total!, elapsed_ms! }` |
| `SearchGroup` | `resource!, label!, icon, count!, has_more!, more_url, items: @SearchItem[]` |
| `SearchItem` | `id!, title!, subtitle, icon, url!, meta, score` |
| `SearchUnavailableResponse` | `success!, payload: @SimpleErrorPayload` (errorKey=`search_unavailable`) |

---

## Health (sister-pack)

| Template | Описание |
|---|---|
| `HealthSummaryResponse` | `success!, payload: { overall!, counts: @HealthCounts, last_run_at!, failing_checks: @HealthFailingItem[] }` |
| `HealthCounts` | `ok!, warning!, failing!` |
| `HealthFailingItem` | `id!, name!, message!` |
| `HealthChecksResponse` | `success!, payload: { checks: @HealthCheckStatus[], last_run_at! }` |
| `HealthCheckStatus` | `id!, name!, category!, status!, message, meta!, frequency!, last_run_at!, duration_ms!` |
| `HealthCheckStatusResponse` | `success!, payload: @HealthCheckStatus` |
| `HealthHistoryResponse` | `success!, payload: { data: @HealthHistoryItem[], meta: @PaginationMeta }` |
| `HealthHistoryItem` | `ran_at!, status!, duration_ms!, message` |

---

## Резюме количества

| Категория | Количество templates |
|---|---|
| Common (envelope, errors, building blocks) | ~30 |
| System | ~13 |
| Auth | ~8 |
| Profile | ~10 |
| Resources & Actions | ~32 |
| Settings | ~4 |
| Screens | ~4 |
| Dashboards & Widgets | ~8 |
| Uploads | ~6 |
| Delayed processes | ~6 |
| Exports & Imports | ~7 |
| Search (sister) | ~4 |
| Health (sister) | ~8 |
| **Всего** | **~140** templates (включая building blocks) |

Все они декларированы в `src/Http/AdminApi.php` через traits `src/Http/Schemas/AdminApi*Schemas.php`.
