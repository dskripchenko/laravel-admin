# API: Auth

Контроллер `auth` (`Dskripchenko\LaravelAdmin\Auth\Controllers\AuthController`) — вход, выход, сброс пароля, подтверждение email, второй шаг 2FA при входе, impersonation.

> Конвенции — [conventions.md](conventions.md). Профиль (смена пароля, настройка 2FA, API-токены) — [profile.md](profile.md).

URL: `/api/admin/auth/{action}`. API работает на сессии (`web`-группа в стеке панели), поэтому POST-запросы проходят CSRF-проверку: браузерный клиент шлёт `X-XSRF-TOKEN` из cookie `XSRF-TOKEN`.

---

## Регистрация в `AdminApi::getMethods()`

```php
'auth' => [
    'controller' => AuthController::class,
    'actions' => [
        'login' => [
            'method' => ['post'],
            'middleware' => [ThrottleRequests::class.':'.config('admin.auth.login_throttle', '5,1').',auth-{panel}'],
            'exclude-middleware' => [AdminAuth::class],
        ],
        'logout' => ['method' => ['post']],
        'forgotPassword' => [
            'method' => ['post'],
            'middleware' => [ThrottleRequests::class.':3,5,forgot-{panel}'],
            'exclude-middleware' => [AdminAuth::class],
        ],
        'resetPassword' => ['method' => ['post'], 'exclude-middleware' => [AdminAuth::class]],
        'verifyEmail'   => ['method' => ['post'], 'exclude-middleware' => [AdminAuth::class]],
        'resendEmailVerification' => [
            'method' => ['post'],
            'middleware' => [ThrottleRequests::class.':3,1,verify-{panel}'],
            'exclude-middleware' => [AdminAuth::class],
        ],
        'twoFactorChallenge' => [
            'method' => ['post'],
            'middleware' => [ThrottleRequests::class.':'.config('admin.auth.login_throttle', '5,1').',auth-{panel}'],
            'exclude-middleware' => [AdminAuth::class],
        ],
        'twoFactorRecovery' => [/* тот же throttle, что у login */],
        'startImpersonation' => ['method' => ['post']],
        'stopImpersonation'  => ['method' => ['post']],
    ],
],
```

`{panel}` — id панели (`AdminApi::panelId()`, у основной панели `admin`). У `login`, `twoFactorChallenge` и `twoFactorRecovery` один и тот же префикс `auth-{panel}`, то есть общий счётчик попыток; лимит задаётся `admin.auth.login_throttle` (env `ADMIN_LOGIN_THROTTLE`, по умолчанию `5,1` — 5 запросов в минуту). Превышение лимита — `429`.

Без входа доступны все actions, кроме `logout`, `startImpersonation` и `stopImpersonation` (на них действует `AdminAuth`, см. [system.md](system.md)).

Пользователь ищется через provider guard'а текущей панели, письма сброса пароля идут через password broker панели (`admin.auth.password_broker` для основной).

---

## Форма пользователя в ответах

`login`, `twoFactorChallenge`, `twoFactorRecovery`, `resetPassword`, `startImpersonation`, `stopImpersonation` возвращают пользователя в одной форме:

| Ключ | Описание |
|---|---|
| `id` | идентификатор |
| `name`, `email`, `avatar` | атрибуты модели как есть |
| `locale`, `theme` | атрибуты модели как есть, без подстановки значений по умолчанию (`null`, если не выбраны) |
| `twoFactorEnabled` | `hasTwoFactorEnabled()` модели, иначе `false` |
| `impersonator` | всегда `null` в этой форме (у `startImpersonation` исходный пользователь — в отдельном ключе ответа) |

`redirect_url` во всех ответах — `/{admin.path}` (по умолчанию `/admin`).

---

## `auth.login`

`POST /api/admin/auth/login` — без аутентификации.

| Параметр | Правила |
|---|---|
| `email` | `required`, `email` |
| `password` | `required`, `string` |
| `remember` | `nullable`, `boolean` |

Порядок проверок:

1. Неверный email или пароль — `401 invalid_credentials` (диспатчится `Illuminate\Auth\Events\Failed`).
2. Учётная запись отключена (`AccountState::isDisabled()`) — `403 account_inactive`.
3. Нет доступа к панели (`PanelAccess::allows()`, актуально для стратегии `shared`) — `403 forbidden`.
4. У пользователя включена 2FA (`hasTwoFactorEnabled()`) — сессия не создаётся, ответ **HTTP 200** с `success: false`:

```json
{
  "success": false,
  "payload": {
    "errorKey": "two_factor_required",
    "message": "Введите код из приложения-аутентификатора",
    "challenge_token": "<64 символа>"
  }
}
```

`challenge_token` хранится в кэше 5 минут (вместе с флагом `remember`); следующий шаг — `auth.twoFactorChallenge` или `auth.twoFactorRecovery`.

5. Иначе — вход: `Auth::guard()->login()`, запись `last_login_at`/`last_login_ip` (если у таблицы есть колонка `last_login_at`), регенерация сессии.

Ответ `200`:

```json
{
  "success": true,
  "payload": {
    "user": { "id": 1, "name": "...", "email": "...", "avatar": null, "locale": null, "theme": null, "twoFactorEnabled": false, "impersonator": null },
    "permissions": ["..."],
    "redirect_url": "/admin"
  }
}
```

`permissions` — плоский список прав пользователя (`UserPermissions::resolve()`).

Ошибка валидации — `422 validation` (`messages` — ошибки по полям).

---

## `auth.twoFactorChallenge`

`POST /api/admin/auth/twoFactorChallenge` — без аутентификации.

| Параметр | Правила |
|---|---|
| `challenge_token` | `required`, `string` — из ответа `login` |
| `code` | `required`, `string` — TOTP из приложения |

Код проверяется с окном `admin.auth.two_factor.window` (по умолчанию `1`). При успехе токен удаляется, выполняется вход (как в п. 5 `login`) и возвращается тот же ответ, что у успешного `login`.

Ошибки: `401 challenge_expired` — токена нет в кэше (истёк или уже использован); `401 invalid_two_factor_code` — неверный код или у пользователя 2FA уже не включена; `422 validation`; `429`.

---

## `auth.twoFactorRecovery`

`POST /api/admin/auth/twoFactorRecovery` — без аутентификации.

| Параметр | Правила |
|---|---|
| `challenge_token` | `required`, `string` |
| `recovery_code` | `required`, `string` — одноразовый код восстановления |

Использованный код удаляется из `two_factor_recovery_codes`. Ответ `200` — как у `login` плюс `recovery_codes_remaining` (сколько кодов осталось).

Ошибки: `401 challenge_expired`; `401 invalid_recovery_code`; `422 validation`; `429`.

---

## `auth.logout`

`POST /api/admin/auth/logout` — требует аутентификации.

Выход из guard'а панели, инвалидация сессии и перегенерация CSRF-токена. Ответ `200`, `payload` — пустой массив.

---

## `auth.forgotPassword`

`POST /api/admin/auth/forgotPassword` — без аутентификации, не чаще 3 раз за 5 минут.

| Параметр | Правила |
|---|---|
| `email` | `required`, `email` |

Отправляет ссылку для сброса через password broker панели. Ответ всегда успешный (защита от перебора адресов):

```json
{ "success": true, "payload": { "message": "Если такой email зарегистрирован, на него отправлено письмо со ссылкой для сброса пароля" } }
```

Ошибки: `422 validation`; `429`.

---

## `auth.resetPassword`

`POST /api/admin/auth/resetPassword` — без аутентификации.

| Параметр | Правила |
|---|---|
| `email` | `required`, `email` |
| `token` | `required`, `string` — токен из письма |
| `password` | `required`, `string`, `min:8`, `confirmed` |
| `password_confirmation` | `required`, `string` |

Пароль сбрасывается через broker (новый `remember_token`, событие `Illuminate\Auth\Events\PasswordReset`). Затем пользователь входит, если учётная запись не отключена и есть доступ к панели.

Ответ `200`: `{"user": {...} | null, "redirect_url": "/admin"}`.

Ошибки: `422 validation` — при ошибке валидации, а также когда broker отклонил сброс (неверный или просроченный токен): тогда сообщение broker'а лежит в `messages.token`.

---

## `auth.verifyEmail`

`POST /api/admin/auth/verifyEmail` — без аутентификации.

| Параметр | Описание |
|---|---|
| `id` | id пользователя |
| `hash` | `sha1` от email пользователя |
| `expires`, `signature` | параметры подписи; запрос должен проходить `URL::hasValidSignature()` |

Пользователь ищется в модели панели и должен реализовывать `MustVerifyEmail`. При успехе ставится отметка о подтверждении и диспатчится `Illuminate\Auth\Events\Verified`.

Ответ `200`: `{"message": "Email подтверждён", "redirect_url": "/admin"}` (или `"Email уже подтверждён"`, если адрес был подтверждён раньше).

Ошибки: `422 validation` — неверная или просроченная подпись URL, пользователь не найден, не реализует `MustVerifyEmail` или `hash` не совпал.

---

## `auth.resendEmailVerification`

`POST /api/admin/auth/resendEmailVerification` — не чаще 3 раз в минуту. `AdminAuth` на action не действует, но нужен вошедший пользователь, реализующий `MustVerifyEmail`.

Ответ `200`: `{"message": "Письмо отправлено"}` или `{"message": "Email уже подтверждён"}`.

Ошибки: `401 unauthenticated` — пользователя нет или модель не реализует `MustVerifyEmail`; `429`.

---

## `auth.startImpersonation`

`POST /api/admin/auth/startImpersonation` — требует аутентификации.

| Параметр | Правила |
|---|---|
| `user_id` | `required`, `integer` |

Настройки — `admin.auth.impersonation`: `enabled`, `permission` (по умолчанию `admin.impersonate`), `block_higher_powered`. Право проверяется в контроллере через `hasAccess()` модели, не через middleware.

Ошибки, в порядке проверок:

| HTTP | errorKey | Условие |
|---|---|---|
| 403 | `impersonation_disabled` | `admin.auth.impersonation.enabled` выключен |
| 422 | `validation` | невалидный `user_id` |
| 403 | `forbidden` | у пользователя нет права из `admin.auth.impersonation.permission` (или модель без `hasAccess()`) |
| 403 | `already_impersonating` | impersonation уже активна |
| 404 | `not_found` | целевой пользователь не найден |
| 403 | `forbidden` | попытка войти под самим собой |
| 403 | `forbidden` | при `block_higher_powered`: у цели есть права, которых нет у текущего пользователя (сравнение `getAllPermissions()`; обладатель `*` проходит всегда) |

Ответ `200`:

```json
{
  "user": { "...": "целевой пользователь" },
  "impersonator": { "id": 1, "name": "..." },
  "redirect_url": "/admin"
}
```

Id исходного пользователя хранится в сессии; `system.me` во время impersonation отдаёт его в `impersonator`.

---

## `auth.stopImpersonation`

`POST /api/admin/auth/stopImpersonation` — требует аутентификации.

Возвращает в сессию исходного пользователя. Ответ `200`: `{"user": {...исходный пользователь}, "impersonator": null, "redirect_url": "/admin"}`.

Ошибки: `400 no_active_impersonation` — impersonation не активна; `400 impersonator_not_found` — исходный пользователь больше не существует (метка impersonation при этом снимается).

---

## Аудит

Если включены `admin.audit.enabled` и `admin.audit.log_auth_events`, `AuthAuditListener` пишет в журнал аудита стандартные события Laravel: `Login`, `Logout`, `Failed`, `PasswordReset`, `Lockout`. Отдельного события для impersonation контроллер не диспатчит: начало и конец impersonation — это обычный `Auth::guard()->login()`, то есть событие `Login`.
