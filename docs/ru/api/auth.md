# API: Auth

Контроллер `auth` — login, logout, password-reset, email-verification, 2FA-challenge во время логина, impersonation.

> Конвенции — [conventions.md](conventions.md). Profile-related (смена пароля, 2FA-setup, API-токены) — в [profile.md](profile.md).

URL: `api/admin/auth/{action}`.

---

## AuthController

### Регистрация

```php
'auth' => [
    'controller' => AuthController::class,
    'actions' => [
        'login'                 => ['method' => ['post'], 'middleware' => [ThrottleRequests::class . ':5,1'], 'exclude-middleware' => [AdminAuth::class]],
        'logout'                => ['method' => ['post']],
        'forgotPassword'        => ['method' => ['post'], 'middleware' => [ThrottleRequests::class . ':3,5'], 'exclude-middleware' => [AdminAuth::class]],
        'resetPassword'         => ['method' => ['post'], 'exclude-middleware' => [AdminAuth::class]],
        'verifyEmail'           => ['method' => ['post'], 'exclude-middleware' => [AdminAuth::class]],
        'resendEmailVerification' => ['method' => ['post'], 'middleware' => [ThrottleRequests::class . ':3,1']],
        'twoFactorChallenge'    => ['method' => ['post'], 'middleware' => [ThrottleRequests::class . ':10,1'], 'exclude-middleware' => [AdminAuth::class]],
        'twoFactorRecovery'     => ['method' => ['post'], 'middleware' => [ThrottleRequests::class . ':10,1'], 'exclude-middleware' => [AdminAuth::class]],
        'startImpersonation'    => ['method' => ['post'], 'middleware' => [AdminAccess::class . ':admin.impersonate']],
        'stopImpersonation'     => ['method' => ['post']],
    ],
],
```

---

## Действия

### `auth.login`

```php
/**
 * Аутентификация по email/паролю.
 * При включённой 2FA для пользователя возвращает challenge_token,
 * который надо использовать в auth.twoFactorChallenge.
 *
 * @input string(email) $email Email администратора.
 * @input string $password Пароль.
 * @input boolean ?$remember Запомнить сессию.
 *
 * @output object $payload AdminUserSummary + redirect.
 * @output object $payload.user Данные пользователя.
 * @output string $payload.redirect_url Куда вести SPA после логина.
 *
 * @security Public
 * @response 200 {LoginResponse}
 * @response 200 {TwoFactorRequiredResponse} 2FA требуется (errorKey=two_factor_required).
 * @response 401 {InvalidCredentialsResponse} Неверные креды или забаненный юзер.
 * @response 422 {ValidationErrorResponse}
 * @response 429 {ThrottledResponse}
 */
public function login(Request $request): JsonResponse;
```

Special-case ответ `two_factor_required`:

```json
{
  "success": false,
  "payload": {
    "errorKey": "two_factor_required",
    "message": "Введите код из приложения-аутентификатора",
    "challenge_token": "..."
  }
}
```

`challenge_token` действителен 5 минут.

Events: `Admin\Events\LoginSucceeded` или `Admin\Events\LoginFailed` (audit).

### `auth.logout`

```php
/**
 * Выйти из сессии.
 * Инвалидирует session, axios сбрасывает auth-state.
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 */
public function logout(Request $request): JsonResponse;
```

Events: `Admin\Events\LoggedOut` (audit).

### `auth.forgotPassword`

```php
/**
 * Запросить отправку письма для сброса пароля.
 * Ответ всегда успешный (даже если email не найден) — защита от user-enumeration.
 *
 * @input string(email) $email Email.
 *
 * @output object $payload
 * @output string $payload.message Сообщение для UI.
 *
 * @security Public
 * @response 200 {GenericMessageResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 429 {ThrottledResponse}
 */
public function forgotPassword(Request $request): JsonResponse;
```

### `auth.resetPassword`

```php
/**
 * Сбросить пароль по токену из письма.
 * При успехе — авто-логин, в payload приходят user + redirect_url.
 *
 * @input string(email) $email
 * @input string $token Токен из URL письма.
 * @input string $password Новый пароль (min:8).
 * @input string $password_confirmation Подтверждение.
 *
 * @output object $payload
 * @output object $payload.user
 * @output string $payload.redirect_url
 *
 * @security Public
 * @response 200 {LoginResponse}
 * @response 422 {ValidationErrorResponse} Token expired/invalid → messages.token.
 */
public function resetPassword(Request $request): JsonResponse;
```

Events: `Admin\Events\PasswordReset` (audit).

### `auth.verifyEmail`

```php
/**
 * Подтвердить email по signed-параметрам из письма.
 *
 * @input integer $id ID пользователя.
 * @input string  $hash Hash из подписи.
 * @input string  $expires Expires-таймстамп из URL.
 * @input string  $signature Подпись.
 *
 * @output object $payload
 * @output string $payload.message
 * @output string $payload.redirect_url
 *
 * @security Public
 * @response 200 {GenericMessageResponse}
 * @response 422 {ValidationErrorResponse} Невалидная подпись или просроченная.
 */
public function verifyEmail(Request $request): JsonResponse;
```

### `auth.resendEmailVerification`

```php
/**
 * Повторно отправить письмо для верификации email.
 *
 * @output object $payload
 * @output string $payload.message
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {GenericMessageResponse}
 * @response 429 {ThrottledResponse}
 */
public function resendEmailVerification(Request $request): JsonResponse;
```

---

## 2FA challenge (во время логина)

### `auth.twoFactorChallenge`

```php
/**
 * Подтвердить TOTP-код после login, вернувшего two_factor_required.
 *
 * @input string $challenge_token Из login-ответа.
 * @input string $code 6-значный TOTP.
 *
 * @output object $payload
 * @output object $payload.user
 * @output string $payload.redirect_url
 *
 * @security Public
 * @response 200 {LoginResponse}
 * @response 401 {InvalidTwoFactorResponse} Неверный код или истёк challenge.
 * @response 422 {ValidationErrorResponse}
 * @response 429 {ThrottledResponse}
 */
public function twoFactorChallenge(Request $request): JsonResponse;
```

### `auth.twoFactorRecovery`

```php
/**
 * Использовать одноразовый recovery-код вместо TOTP.
 * Использованный код инвалидируется. При остатке ≤2 кодов SPA показывает warning.
 *
 * @input string $challenge_token
 * @input string $recovery_code Одноразовый.
 *
 * @output object $payload
 * @output object $payload.user
 * @output string $payload.redirect_url
 * @output integer $payload.recovery_codes_remaining Осталось кодов.
 *
 * @security Public
 * @response 200 {RecoveryLoginResponse}
 * @response 401 {InvalidRecoveryCodeResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function twoFactorRecovery(Request $request): JsonResponse;
```

---

## Impersonation

### `auth.startImpersonation`

```php
/**
 * Войти под другим пользователем.
 * Требует admin.impersonate. Опция block_higher_powered может запретить
 * impersonate юзеров с большим набором прав.
 *
 * @input integer $user_id ID целевого пользователя.
 *
 * @output object $payload
 * @output object $payload.user Целевой пользователь.
 * @output object $payload.impersonator Оригинал.
 * @output integer $payload.impersonator.id
 * @output string  $payload.impersonator.name
 * @output string $payload.redirect_url
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ImpersonationResponse}
 * @response 403 {ForbiddenErrorResponse} Нет admin.impersonate либо блокировка по power.
 * @response 404 {NotFoundErrorResponse} Юзер не найден.
 */
public function startImpersonation(Request $request): JsonResponse;
```

Events: `Admin\Events\ImpersonationStarted` (audit с `impersonator_id` + `target_id`).

### `auth.stopImpersonation`

```php
/**
 * Завершить impersonation, вернуться в свою сессию.
 *
 * @output object $payload
 * @output object $payload.user Оригинальный пользователь.
 * @output null   $payload.impersonator Всегда null.
 * @output string $payload.redirect_url
 *
 * @security AdminSession
 * @response 200 {LoginResponse}
 * @response 400 {NoActiveImpersonationResponse} Нет активной impersonation.
 */
public function stopImpersonation(Request $request): JsonResponse;
```

Events: `Admin\Events\ImpersonationStopped` (audit).
