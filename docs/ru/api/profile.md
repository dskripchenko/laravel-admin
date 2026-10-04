# API: Profile

Контроллер `profile` (`Dskripchenko\LaravelAdmin\Profile\Controllers\ProfileController`) — профиль текущего пользователя, смена пароля, настройка 2FA, коды восстановления, персональные API-токены (Sanctum).

> Вход, выход и второй шаг 2FA при входе — [auth.md](auth.md). Конвенции — [conventions.md](conventions.md).

URL: `/api/admin/profile/{action}`. Все actions требуют аутентификации (`AdminAuth` из стека панели, см. [system.md](system.md)) и работают только с текущим пользователем guard'а панели. Отдельных прав не требуется.

---

## Регистрация в `AdminApi::getMethods()`

```php
'profile' => [
    'controller' => ProfileController::class,
    'actions' => [
        'show'                     => ['method' => ['get']],
        'update'                   => ['method' => ['post']],
        'changePassword'           => ['method' => ['post']],
        'twoFactorStatus'          => ['method' => ['get']],
        'twoFactorEnable'          => ['method' => ['post']],
        'twoFactorConfirm'         => ['method' => ['post']],
        'twoFactorDisable'         => ['method' => ['post']],
        'twoFactorRegenerateCodes' => ['method' => ['post']],
        'tokensList'               => ['method' => ['get']],
        'tokenCreate'              => ['method' => ['post']],
        'tokenRevoke'              => ['method' => ['post']],
    ],
],
```

Actions `token*` регистрируются всегда; без Sanctum они отвечают ошибкой (см. [API-токены](#api-токены-sanctum)).

---

## Форма пользователя профиля

`show` и `update` возвращают `user` в такой форме:

| Ключ | Описание |
|---|---|
| `id`, `name`, `email` | данные пользователя |
| `locale`, `theme` | атрибуты модели как есть (`null`, если не выбраны) |
| `is_active` | `(bool) is_active` |
| `email_verified_at` | ISO-8601 или `null` |

---

## Профиль

### `profile.show`

`GET /api/admin/profile/show`

Ответ `200`:

| Ключ | Описание |
|---|---|
| `user` | пользователь (форма выше) |
| `available_locales` | `admin.ui.available_locales` |
| `available_themes` | всегда `["light", "dark"]` |
| `two_factor` | `{enabled, confirmed_at, recovery_codes_remaining}` — как у `twoFactorStatus` |
| `api_tokens_enabled` | сейчас всегда `false` |

### `profile.update`

`POST /api/admin/profile/update`

Все поля необязательны; меняются только переданные.

| Параметр | Правила |
|---|---|
| `name` | `sometimes`, `string`, `max:255` |
| `email` | `sometimes`, `email`, уникален в таблице пользователя (кроме самого пользователя) |
| `locale` | `sometimes`, `string`, одно из `admin.ui.available_locales` |
| `theme` | `sometimes`, `string`, `light` или `dark` |

При смене email сбрасывается `email_verified_at` (письмо подтверждения не отправляется). Ответ `200`: `{"user": {...}}` — перечитанный из БД.

Ошибки: `422 validation`.

### `profile.changePassword`

`POST /api/admin/profile/changePassword`

| Параметр | Правила |
|---|---|
| `current_password` | `required`, `string` |
| `password` | `required`, `string`, `min:8`, `confirmed` |
| `password_confirmation` | должен совпадать с `password` |

Новый пароль записывается в атрибут `password` как есть — хэширует его каст модели (у `AdminUser` — `hashed`). Хэш пароля в текущей сессии обновляется, поэтому текущая сессия остаётся рабочей, а остальные сессии пользователя на следующем запросе получают `401 session_expired` от `AdminAuth`. Диспатчится `Illuminate\Auth\Events\PasswordReset`.

Ответ `200`, `payload` — пустой массив.

Ошибки: `422 validation`; при неверном текущем пароле — `422` с `errorKey: "validation"` и `messages.current_password`.

---

## Настройка 2FA

Используются атрибуты `two_factor_secret`, `two_factor_recovery_codes`, `two_factor_confirmed_at`. 2FA считается включённой, когда заданы и секрет, и `two_factor_confirmed_at`.

### `profile.twoFactorStatus`

`GET /api/admin/profile/twoFactorStatus`

Ответ `200`:

```json
{ "enabled": true, "confirmed_at": "2026-01-01T10:00:00+00:00", "recovery_codes_remaining": 8 }
```

Секрет и коды этот action не возвращает.

### `profile.twoFactorEnable`

`POST /api/admin/profile/twoFactorEnable`

Параметров нет. Генерирует новый секрет и набор кодов восстановления (`admin.auth.two_factor.recovery_codes`, по умолчанию 8), сбрасывает `two_factor_confirmed_at`. До подтверждения через `twoFactorConfirm` 2FA выключена — в том числе если она была включена до вызова.

Ответ `200`:

| Ключ | Описание |
|---|---|
| `qr_code_svg` | всегда `null` — QR рисует клиент по `qr_uri` |
| `secret` | Base32-секрет для ручного ввода |
| `qr_uri` | `otpauth://`-URI; issuer — `admin.brand.name`, аккаунт — email |
| `recovery_codes` | коды восстановления |

### `profile.twoFactorConfirm`

`POST /api/admin/profile/twoFactorConfirm`

| Параметр | Правила |
|---|---|
| `code` | `required`, `string` — TOTP |

Код проверяется с окном `admin.auth.two_factor.window`. Ответ `200`: `{"enabled": true, "confirmed_at": "<ISO-8601>"}`.

Ошибки: `422 validation`; `422 two_factor_not_initialised` — секрета нет (сначала `twoFactorEnable`); `422 invalid_two_factor_code`.

### `profile.twoFactorDisable`

`POST /api/admin/profile/twoFactorDisable`

| Параметр | Правила |
|---|---|
| `password` | `required`, `string` — текущий пароль |

Очищает секрет, коды и `two_factor_confirmed_at`. Ответ `200`, `payload` — пустой массив.

Ошибки: `422 validation`; при неверном пароле — `422` с `errorKey: "validation"` и `messages.password`.

### `profile.twoFactorRegenerateCodes`

`POST /api/admin/profile/twoFactorRegenerateCodes`

| Параметр | Правила |
|---|---|
| `password` | `required`, `string` — текущий пароль |

Заменяет коды восстановления новым набором. Ответ `200`: `{"recovery_codes": [...]}`.

Ошибки: те же, что у `twoFactorDisable`.

---

## API-токены (Sanctum)

Работают, когда установлен `laravel/sanctum` (в `composer.json` он в `suggest`), включён `admin.auth.api_tokens.enabled` (по умолчанию `true`) и модель пользователя использует `HasApiTokens`. Иначе каждый action отвечает `404` с `errorKey: "sanctum_unavailable"`.

### `profile.tokensList`

`GET /api/admin/profile/tokensList`

Ответ `200`: `{"data": [...]}`, токены текущего пользователя, новые первыми:

| Ключ | Описание |
|---|---|
| `id` | id токена |
| `name` | имя |
| `abilities` | список abilities |
| `last_used_at`, `expires_at`, `created_at` | ISO-8601 или `null` |

### `profile.tokenCreate`

`POST /api/admin/profile/tokenCreate`

| Параметр | Правила |
|---|---|
| `name` | `required`, `string`, `max:255` |
| `abilities` | `nullable`, `array`; по умолчанию `["*"]` |
| `abilities.*` | `string` |
| `expires_in_days` | `nullable`, `integer`, `min:1`, `max:3650`; без параметра срок берётся из `admin.auth.api_tokens.default_expiry` (дни; `null` по умолчанию — бессрочный), явный `null` — бессрочный токен |

Ответ `200`:

```json
{
  "plain_text_token": "1|...",
  "token": { "id": 1, "name": "CI", "abilities": ["*"], "expires_at": null }
}
```

`plain_text_token` возвращается только в этом ответе.

Ошибки: `404 sanctum_unavailable`; `422 validation`.

### `profile.tokenRevoke`

`POST /api/admin/profile/tokenRevoke`

| Параметр | Правила |
|---|---|
| `id` | `required`, `integer` |

Удаляет токен, если он принадлежит текущему пользователю. Ответ `200`, `payload` — пустой массив.

Ошибки: `404 sanctum_unavailable`; `404 not_found` — токена нет у текущего пользователя; `422 validation`.
