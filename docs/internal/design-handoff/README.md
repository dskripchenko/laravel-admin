# Handoff: Laravel Admin SPA

## Overview
Single-page admin panel (SPA) for Laravel/Filament-style applications: shell with sidebar + topbar, resource list with bulk actions and inline edit, resource form/view, dashboard, authentication (login + 2FA), import wizard, profile, notifications drawer, and a comprehensive field gallery.

UI copy is mixed: **Russian** for content/labels and **English** for component-level / structural labels (per the original design brief).

## About the Design Files
The HTML/JSX/CSS files in this bundle are **design references** — interactive prototypes that demonstrate intended look, layout, and behaviour. They are **not production code to copy directly**.

The task is to **recreate these designs in the target codebase's existing environment** (Laravel + Inertia/Vue, Filament v3+, Nova, or a separate Vue/React SPA) using that codebase's established libraries and patterns. If no environment is established yet, choose the most appropriate stack — Laravel + Filament v3 is the obvious match given the brief, but a decoupled Vue 3 + Pinia + Tailwind SPA on top of a Laravel API works equally well.

## Fidelity
**High-fidelity.** Final colors, typography, spacing, and interactions are deliberate and should be matched exactly. The visual language is the **UID Design System** — minimalist, "Linear-feeling," zinc-neutral with a single teal accent.

## Design system
- **Tokens** live in `uid/colors_and_type.css` — three-layer model: primitives → semantics → component aliases. Light + dark themes are pre-defined; components reference semantic vars only (`--uid-text-primary`, `--uid-accent`, etc.). Recreate this token layer in the target codebase (Tailwind `theme.extend.colors`, CSS custom properties, or design-tokens-style JSON).
- **Typography**: `Inter` (variable, body) + `Inter Display` (h1/h2 only, tighter cut) + `IBM Plex Serif` (editorial, opt-in) + `IBM Plex Mono` (code).
- **Type scale**: 12 / 14 / 16 / 18 / 20 / 24 / 30 / 36 px. Headings letter-spacing −0.015 to −0.02em.
- **Spacing**: 4 px step (`2 / 4 / 8 / 16 / 24 / 32 / 48 / 64`).
- **Radii**: 3 / 6 / 10 / 9999.
- **Motion**: 100 / 200 / 350 ms with ease-out / in-out cubic-beziers.
- **Density**: two presets — *comfortable* (40 px row, 14 px text) and *compact* (32 px row, 13 px text). Compact is the Linear/Supabase target.

## Screens

### 01 · Login (`LoginScreen`)
Centered 400 px card, brand logo, email + password fields, "remember me" + forgot link, primary button, SSO link below. Theme + locale toggles in top-right corner.

### 02 · 2FA Challenge (`TwoFactor`)
Same auth shell, 6-cell mono code input (44×52, 22 px IBM Plex Mono), recovery-code link.

### 03 · Shell
- **Sidebar** (240 px expanded / 56 px collapsed): brand row, tenant switcher, grouped nav (Контент / Аналитика / Настройки), version+docs footer. Active item indicated by 2 px teal rail at left edge + bold text + subtle background. Four variants exposed: `grouped` (default), `flat`, `zebra`, `iconish` (each item icon in its own rounded square). Sticky, full-height, scrolling nav middle.
- **Topbar** (56 px): collapse-toggle, breadcrumbs, ⌘K command-palette pill (220 px min), bell with unread count badge, theme toggle, locale, avatar.
- **Impersonation banner** (32 px, amber): fixed top, only shown in impersonation mode, with "exit" button.

### 04 · Resource List (`ResourceList`)
- Page header: title + total/visible count + "polling" indicator (pulsing teal dot + "обновлено только что"). Action cluster on right: saved-view dropdown, more, import, **Создать** primary.
- **Filter bar** — three exposed variants:
  - `bar` (default): search field, status/author/category/published chips, "+ Filter", reset, columns/saved-view on right.
  - `chips`: applied filters as removable solid chips, "+ добавить фильтр" outlined.
  - `panel`: row of labeled chips with `label: value` pattern, search above.
- **Group-by chip strip** (toggle): «Все», «Published», «Draft» counts, segmented.
- **Table**: 10 columns, sticky header, sortable headers (`arrow-up-down` muted, active arrow solid), inline-edit cell on dbl-click (becomes input with teal focus ring), summary footer row, row hover, selected row tint (teal 8%), polled row flash (success 22% → transparent, 1.4 s).
- **Bulk toolbar** replaces filter bar when selection > 0: dark surface (`zinc-900`), "Выбрано N", "Выбрать все 1284" link, action buttons (Опубликовать, Архивировать, Экспорт, Удалить-danger).
- **States**: ideal / loading (8 skeleton rows with shimmer) / empty (illustration in muted square + CTA) / error (cloud-off icon, 503, copy trace-id).
- **Pagination footer**: page numbers + per-page select.

### 05 · Resource Form (`ResourceForm`)
- Header: back-breadcrumb, title, status badge + last-edit meta. Actions: Preview, more, Удалить (danger), Сохранить (primary).
- Tabbed (Содержимое / SEO / Медиа / Метаданные) — SEO tab shows error-count badge.
- **Two-column grid**: 1fr main / 320 px side. Main = form blocks (Основное → Содержимое → Связи). Side = sticky stack (Публикация, Обложка, Локализация).
- WYSIWYG mock with toolbar (heading-1/2, bold, italic, underline, strike, code, quote, list, ordered, link, image, table) + undo/redo + word/read-time footer.
- Tags input with chip removal.
- Repeater (`grip-vertical` handle, inline cards).
- Translatable component: tab strip RU/EN/DE under input.
- **Sticky save bar** (bottom): "несохранённые изменения" warning chip on left, ghost-cancel + Сохранить и продолжить + primary Сохранить on right.

### 06 · Resource View / Infolist (`ResourceView`)
Read-only mirror of form layout. Right column: Метрики (key-value list). Left: Основные данные + **audit timeline** with avatar+verb pattern ("опубликовал", "отредактировал", "добавил тег").

### 07 · Dashboard (`Dashboard`)
12-col grid of widgets:
1. **KPI strip** (col-span 12, 4 cells): Total / Page views / Avg read time / In review with delta arrows (`↗`/`↘`/`—`).
2. **Bar chart** (col-span 8): 30-day publications.
3. **Donut + legend** (col-span 4): status distribution.
4. **Recent table** (col-span 8).
5. **Heatmap** (col-span 4): 7×24 grid, alpha-modulated teal cells.
6. **Gauge** (col-span 4): SEO score arc.
7. **Markdown note** (col-span 4): team note widget.

### 08 · Profile (`Profile`)
Two-col 200 / 1fr — left nav (Основное, Безопасность, API токены, Сессии). Right: profile card + 2FA card with success badge.

### 09 · Import Wizard (`ImportWizard`)
Centered max-width 1100. Stepper (Загрузка → Сопоставление → Предпросмотр → Импорт) with completed/current/upcoming states. Per-step: drop zone, mapping rows (file column → resource field with sample), preview table with warning rows, progress bar with created/updated/errors KPIs.

### 10 · Notifications drawer (`NotificationDrawer`)
400 px right drawer + backdrop. Tabs (Все / Непрочитанные / Прочитанные). Items: kind-coloured icon square, title, body, timestamp, unread dot + tinted background.

### 11 · Field Gallery (`FieldGallery`)
3-col grid of demo cards covering 8 groups: Текстовые (Input, Number, Textarea, Code), Выбор (Select, Radio, Checkbox group, Switch), Дата/время (DatePicker, DateRange, TimePicker), Прочее (Color, Slider, Rating, FileUpload), Связи (RelationSelect, MorphSwitcher, RelationTable), Контент (WYSIWYG mini, Slug, KeyValue, TagsInput), Иерархия (TreeSelect, Cascader), Многоязычные (Translatable).

## Interactions & Behavior
- **Theme**: `data-theme="light|dark"` on `<html>`. Token cascade does the rest. Smooth toggle from topbar.
- **Density**: `data-density="comfortable|compact"` on `<html>`. Affects row height, header height, page-title size.
- **Sidebar collapse**: grid template column animates 240 → 56 px (200 ms ease-out). Labels, counts, group headings hide via `display: none` selector chain.
- **Polling**: pulsing teal dot every 1.6 s; periodic row flash (random row from top 3, every 6 s); 4 s after mount, info toast slides in from top-right (240 ms cubic-bezier).
- **Inline edit**: `cell-edit` zone on dbl-click → becomes input with `border + 2 px teal outline-offset 0` focus ring. `Enter` / `Esc` / blur to commit-or-cancel.
- **Bulk select**: header checkbox tri-state (off / mixed / all), selected rows tinted, toolbar replaces filter bar.
- **Toasts**: 4 kinds (info/success/warning/error). 320 px card, slide-in 240 ms, `lg` shadow.
- **Drawer**: backdrop fade-in (200 ms) + slide-l (240 ms cubic-bezier).
- **Modal**: backdrop fade-in + scale 0.96 → 1 pop (240 ms).

## State management
- `view`: `prototype | canvas | gallery`
- `screen`: `list | form | view | dashboard | profile | import`
- `dark`, `density`, `sidebarCollapsed`, `sidebarVariant`, `brandColor`, `impersonation`, `bulkMode`, `polling`
- `listState`: `ideal | loading | empty | error`
- `filterVariant`: `bar | chips | panel`
- For real implementation: pull list from paginated API, stream live updates via WebSocket / SSE / polling, persist saved views on backend, persist user prefs (theme, density, locale) on user model.

## Files in this bundle
- `index.html` + `app.css` + `app.jsx` + `components.jsx` + `screens-shell.jsx` + `screens-secondary.jsx` + `canvas.jsx` — main interactive prototype
- `tweaks-panel.jsx` — local tweaking UI (not for production)
- `uid/colors_and_type.css` + `uid/fonts/*` — full design-token layer + self-hosted Inter/Inter Display fonts
- `index-print.html` — paginated print/PDF version of all screens

## Implementation suggestions
- **If using Filament v3+**: most of this maps directly. Customize the panel theme via `colors()` to match teal accent, override the topbar to add the polling indicator, use Filament's table builder for the resource list (filters, bulk actions, columns toolbox already match), Form/Schema for the form. Rebuild the dashboard widgets as `Widget` classes.
- **If using Inertia + Vue**: lift `app.css` token layer into a Tailwind config (extend colors with the zinc/teal palette, set `fontFamily.sans = ['Inter', ...]`), build the components shown in `components.jsx` as Vue 3 SFCs, and use Inertia for navigation between screens.
- **Icons**: Lucide is used throughout. Keep it.
- **Don't ship the inline `<script type="text/babel">`** approach — it's prototype-only. Use Vite + a real bundler.
