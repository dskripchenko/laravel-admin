# Theming

## Включить темы

Default `available_themes = ['light', 'dark']`. Расширить:

```php
// config/admin.php
'ui' => [
    'default_theme' => 'light',
    'available_themes' => ['light', 'dark', 'sepia'],
],
```

## Endpoints

- `GET /api/admin/system/theme` → `{current, default, available}`.
- `POST /api/admin/system/setTheme` `{theme: 'dark'}` — persist в
  user.theme + cookie (1 год). Public (доступно до логина).

## Приоритет резолва (ThemeManager)

1. `$user->theme` — если залогинен.
2. Cookie `admin_theme`.
3. `config('admin.ui.default_theme')`.

## CSS-vars overrides

SPA выставляет `<html data-theme="{value}">`. Host-проект может
переопределить переменные:

```css
[data-theme="sepia"] {
  --admin-bg: #f4ecd8;
  --admin-fg: #5b4636;
  --admin-primary: #8b4513;
}
```

## Locale аналогично

```bash
GET  /api/admin/system/locales         # {current, available, default}
POST /api/admin/system/setLocale       # {locale: 'en'}
```

5-step priority chain: `?locale=...` → `X-Admin-Locale` header → user.locale
→ cookie `admin_locale` → `Accept-Language` (full-match + short-form)
→ `config('admin.ui.default_locale')`.

## Translatable fields

Для переводимого контента (article title, description) используйте
`TranslatableInput`:

```php
TranslatableInput::make('title')
    ->as('input')                  // input | textarea | markdown | wysiwyg
    ->locales(['ru', 'en', 'de'])  // override config'а
    ->requireAllLocales();
```

State: `{ru: 'Привет', en: 'Hello', de: 'Hallo'}`. На backend'е сохраняются
через `dskripchenko/laravel-translatable` если модель использует
`TranslationTrait`.
