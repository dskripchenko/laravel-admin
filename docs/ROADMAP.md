# Roadmap — статус фаз

Статус выполнения плана из [ARCHITECTURE.md §12](ARCHITECTURE.md) (там —
скоуп и оценки). Обновляется при закрытии фаз и major-вехах.

Актуально на 2026-07-22 (v1.9.0 / npm 1.7.0).

## Core

| Фаза | Статус | Примечание |
|---|---|---|
| P0. Скаффолд | ✅ | |
| P1. Backbone | ✅ | |
| P2. Auth & RBAC | ✅ | 2FA TOTP, impersonation, profile |
| P3. Resource v1 | ✅ | manifest etag/version |
| P4. Базовые Field | ✅ | |
| P5. Сложные Field | ✅ | Builder — базовый; см. Backlog |
| P6. Tables advanced | ✅ | inline-edit, saved views, summarizers, group-by |
| P7. Layouts/primitives | ✅ | Wizard, Infolist + Entries |
| P8. Widgets + Dashboard | ✅ | |
| P9. Resource extras | ✅ | soft-delete, replicate, reorder |
| P10. Audit | ✅ | + auth-события (single-dispatch с 1.8.5) |
| P11. Settings + Plugin + Tenancy | ✅ | |
| P12. Actions advanced | ✅ | async через delayed-process |
| P13. Export/Import | ✅ | XLSX/PDF/CSV, import-wizard |
| P14. WYSIWYG | ✅ | default — @dskripchenko/wysiwyg; tinymce/quill sister-packs |
| P15. Notifications + API tokens | ✅ | |
| P16. Theming + i18n | ✅ | |
| P17. Bootstrap + Scalar UI | ✅ | |
| P18. Тесты + helpers | ✅ | 857 backend / 325 frontend на 1.9.0 |
| P19. Документация + примеры | ✅ | en/ru/de/zh + demo |
| **P20. Бета (пилот)** | ✅ | **Закрыта серией 1.7.x–1.8.9 на пилоте printable**: staging-стенд, мультитенантность (schemify-слои), две панели, E2E-прогон сценариев обеих панелей (28/28). Найдено и исправлено на пилоте: guest-manifest 500, panel-aware auth, throttle-дубли и общие бакеты, event-дубли Login/Logout, snake_case field-регистрация, RelationSelect options, mode-visibility полей, unique auto-ignore, DB-422 messages, SPA-permissions hasAccess-only моделей, префилл Field::default() |
| P21. Canon-матрица версий | ✅ | PHP 8.2–8.5 × Laravel 11/12/13 |
| M1. Panels | ✅ | v1.8.0 — независимые поверхности (Filament-parity), guard/provider/broker per panel |

**v1.9.0 — стабильный срез: core v1.0-скоуп выполнен полностью.**

## Frontend (npm @dskripchenko/laravel-admin)

| Веха | Статус | Примечание |
|---|---|---|
| F1–F9 + F-refactor.0 | ✅ | на @dskripchenko/ui |
| P22 ScreenPage, M1 AdminSidebarNode | ✅ | n-уровневое меню |
| F10. Dashboard | ✅ | npm 1.8.0+1.8.1 / composer 1.9.1 (2026-07-22, E2E 8/8 на стенде): DashboardPage — 12-col grid, edit-mode (drag-reorder / resize-span / hide / remove), AddWidgetDialog из реестра, per-user персистенция (/dashboard/get|save|reset, DashboardLayout); полный набор widget-компонентов под backend-типы: stats/chart(+bar/donut)/recent_list/heatmap/gauge/markdown/**table** (resource-колонки + formatCell)/**iframe** (sandbox); 1.8.1: сидирование draft при входе в edit (первый save слал widgets:[] → 422), кнопка «Сбросить» (reset был без UI), i18n тулбара |

## Sister-packs

| Пакет | Статус |
|---|---|
| starter, search, media, health, pulse, jobs, tinymce, quill | ✅ v1.0 |

## Backlog — закрыт 2026-07-22 (core v1.9.2 + npm 1.9.0)

- ✅ SPA-компоненты сложных полей: `key_value`, `repeater`, `builder`,
  `relation_table` (npm 1.9.0; вложенные под-формы через NestedFieldsGroup).
- ✅ `admin:user --super` — назначает системную роль Super Admin (v1.9.2).
- ✅ Session invalidation: смена пароля гасит остальные сессии
  (AuthenticateSession-семантика в AdminAuth, 401 session_expired);
  выключенная учётка теряет доступ на следующем запросе (v1.9.2).
- ✅ Object-rules доезжают до валидатора (ValidationRulesExporter, v1.9.2) —
  включило composite-unique per-field 422 до DB в printable (ScopedUniqueRule).
- ✅ printable: CSRF-исключение сужено до stateless-поверхностей (§3.1);
  rate-limit внешнего API ключуется credential-token'ом, internal — ip+client.
