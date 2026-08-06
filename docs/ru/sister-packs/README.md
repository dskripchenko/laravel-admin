# Sister-packs

Каталог спецификаций опциональных расширений `dskripchenko/laravel-admin`.

Каждый sister-pack — отдельный composer-пакет под `dskripchenko/`, MIT-лицензия, реализован как `AdminPlugin` (контракт см. `../ARCHITECTURE.md` п.5.23). Не подключается по умолчанию — только явное `composer require`.

## v1.0 (планируется параллельно с core)

| Пакет | Файл | Назначение |
|---|---|---|
| `dskripchenko/laravel-admin-starter` | [starter.md](starter.md) | Готовые системные Resource'ы (Users/Roles/Audit/Settings/Translations/Blocks) |
| `dskripchenko/laravel-admin-tinymce` | [tinymce.md](tinymce.md) | Альтернативный WYSIWYG (TinyMCE) |
| `dskripchenko/laravel-admin-quill` | [quill.md](quill.md) | Альтернативный WYSIWYG (Quill) |
| `dskripchenko/laravel-admin-search` | [search.md](search.md) | Глобальный поиск (cmd+K) |
| `dskripchenko/laravel-admin-media` | [media.md](media.md) | Медиа-библиотека с коллекциями/тегами/focal-point |
| `dskripchenko/laravel-admin-health` | [health.md](health.md) | Health-checks dashboard |
| `dskripchenko/laravel-admin-pulse` | [pulse.md](pulse.md) | Лёгкая телеметрия |
| `dskripchenko/laravel-admin-jobs` | [jobs.md](jobs.md) | Failed jobs / queue viewer |

## v1.x+ (план развития, оценка по запросу)

- `laravel-admin-sso` — OIDC / SAML 2.0 / Google / Microsoft / Keycloak.
- `laravel-admin-webauthn` — security-keys (FIDO2) как альтернатива TOTP.
- `laravel-admin-mail-preview` — UI для просмотра mailable'ов.
- `laravel-admin-cron` — UI для `schedule:list` + история запусков.
- `laravel-admin-translate-cli` — массовый автоперевод (DeepL/OpenAI/Yandex).

## Шаблон спецификации

Каждый sister-pack описан по единой схеме:

1. **Назначение** (одна фраза + use case).
2. **Состав** (какие Resource/Widget/Field/Action/Middleware добавляет).
3. **Зависимости** (composer + npm; что в `require`, что в `suggest`).
4. **Миграции** (если есть).
5. **Permissions** (какие группы регистрирует в `ItemPermission`).
6. **Конфиг** (`config/admin-{slug}.php`).
7. **Подключение** (composer/npm-команды + artisan).
8. **Зачем sister, а не core** (обоснование).
