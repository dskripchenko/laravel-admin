# API: Profile

Контроллер `profile` — профиль текущего администратора, смена пароля, 2FA-setup, recovery codes, API-токены (Sanctum).

> Login/logout/2FA-challenge — в [auth.md](auth.md). Конвенции — в [conventions.md](conventions.md).

URL: `api/admin/profile/{action}`. Все actions требуют `AdminSession` или `AdminBearer`.

---

## ProfileController

### Регистрация

```php
'profile' => [
    'controller' => ProfileController::class,
    'middleware' => [AdminAuth::class],
    'actions' => [
        'show'                       => ['method' => ['get']],
        'update'                     => ['method' => ['post']],
        'changePassword'             => ['method' => ['post']],
        'twoFactorStatus'            => ['method' => ['get']],
        'twoFactorEnable'            => ['method' => ['post']],
        'twoFactorConfirm'           => ['method' => ['post']],
        'twoFactorDisable'           => ['method' => ['post']],
        'twoFactorRegenerateCodes'   => ['method' => ['post']],
        'tokensList'                 => ['method' => ['get']],
        'tokenCreate'                => ['method' => ['post']],
        'tokenRevoke'                => ['method' => ['post']],
        'tokenRegenerate'            => ['method' => ['post']],
    ],
],
```

> Actions `tokens*` доступны только при установленном `laravel/sanctum` и `config/admin.php → auth.api_tokens.enabled = true`. Иначе их регистрация пропускается, и URL отдают 404.

---

## Profile

### `profile.show`

```php
/**
 * Получить профиль текущего пользователя.
 *
 * @output object $payload
 * @output object $payload.user AdminUserSummary с расширенными полями (locale, theme, ...).
 * @output array  $payload.available_locales
 * @output array  $payload.available_themes light|dark
 * @output object $payload.two_factor
 * @output boolean $payload.two_factor.enabled
 * @output string(date-time) ?$payload.two_factor.confirmed_at
 * @output integer $payload.two_factor.recovery_codes_remaining
 * @output boolean $payload.api_tokens_enabled Зависит от наличия Sanctum.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ProfileResponse}
 * @response 401 {UnauthenticatedErrorResponse}
 */
public function show(Request $request): JsonResponse;
```

### `profile.update`

```php
/**
 * Обновить базовые поля профиля (имя, email, локаль, тема, аватар).
 * При смене email — отправляется письмо для верификации, email_verified_at обнуляется.
 *
 * @input string ?$name Имя.
 * @input string(email) ?$email Email.
 * @input string ?$locale Код локали (должен быть в available_locales).
 * @input string ?$theme light|dark.
 * @input string(uuid) ?$avatar_id ID upload'а из uploads.upload (null = удалить аватар).
 *
 * @output object $payload
 * @output object $payload.user
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ProfileUpdateResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function update(Request $request): JsonResponse;
```

Events: `Admin\Events\ProfileUpdated` (audit).

### `profile.changePassword`

```php
/**
 * Сменить пароль.
 *
 * @input string $current_password Текущий пароль для re-auth.
 * @input string $password Новый пароль (min:8).
 * @input string $password_confirmation Подтверждение.
 * @input boolean ?$revoke_other_sessions Завершить все остальные сессии (default false).
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 422 {ValidationErrorResponse} current_password неверен.
 */
public function changePassword(Request $request): JsonResponse;
```

Events: `Admin\Events\PasswordChanged` (audit).

---

## 2FA Setup

### `profile.twoFactorStatus`

```php
/**
 * Получить текущий статус 2FA. При state="pending" возвращает qr_code/secret/recovery_codes
 * (один раз, до подтверждения).
 *
 * @output object $payload
 * @output boolean $payload.enabled Включена ли 2FA.
 * @output string(date-time) ?$payload.confirmed_at Когда подтверждена.
 * @output string  ?$payload.qr_code_svg SVG inline (только при pending).
 * @output string  ?$payload.secret Base32-секрет для manual-ввода (только при pending).
 * @output string  ?$payload.qr_uri otpauth://... (только при pending).
 * @output array   ?$payload.recovery_codes Список одноразовых кодов (только при pending или после регенерации).
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {TwoFactorStatusResponse}
 */
public function twoFactorStatus(Request $request): JsonResponse;
```

### `profile.twoFactorEnable`

```php
/**
 * Сгенерировать новый secret и recovery codes. После этого state=pending —
 * пользователь должен подтвердить TOTP-кодом через twoFactorConfirm.
 *
 * @input string ?$password Re-auth, требуется если включено в config.
 *
 * @output object $payload
 * @output string $payload.qr_code_svg
 * @output string $payload.secret
 * @output string $payload.qr_uri
 * @output array  $payload.recovery_codes 8 кодов.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {TwoFactorSetupResponse}
 * @response 422 {ValidationErrorResponse} Неверный password.
 */
public function twoFactorEnable(Request $request): JsonResponse;
```

### `profile.twoFactorConfirm`

```php
/**
 * Подтвердить включение 2FA вводом TOTP-кода с того же устройства.
 *
 * @input string $code 6-значный TOTP.
 *
 * @output object $payload
 * @output boolean $payload.enabled Всегда true.
 * @output string(date-time) $payload.confirmed_at Текущее время.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {TwoFactorConfirmedResponse}
 * @response 422 {InvalidTwoFactorResponse}
 */
public function twoFactorConfirm(Request $request): JsonResponse;
```

Events: `Admin\Events\TwoFactorEnabled` (audit).

### `profile.twoFactorDisable`

```php
/**
 * Отключить 2FA.
 *
 * @input string $password Re-auth.
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 422 {ValidationErrorResponse} Неверный password.
 */
public function twoFactorDisable(Request $request): JsonResponse;
```

Events: `Admin\Events\TwoFactorDisabled` (audit).

### `profile.twoFactorRegenerateCodes`

```php
/**
 * Сгенерировать новый набор recovery-кодов. Старый инвалидируется.
 *
 * @input string $password Re-auth.
 *
 * @output object $payload
 * @output array  $payload.recovery_codes 8 новых кодов (показываются один раз).
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {RecoveryCodesResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function twoFactorRegenerateCodes(Request $request): JsonResponse;
```

Events: `Admin\Events\TwoFactorRecoveryCodesRegenerated` (audit).

---

## API Tokens (Sanctum)

### `profile.tokensList`

```php
/**
 * Получить список своих API-токенов.
 *
 * @output object $payload
 * @output array  $payload.data Список ApiToken.
 * @output integer $payload.data[].id
 * @output string  $payload.data[].name
 * @output array   $payload.data[].abilities ['admin.users.view', '*'].
 * @output string(date-time) ?$payload.data[].last_used_at
 * @output string(date-time) $payload.data[].created_at
 * @output string(date-time) ?$payload.data[].expires_at
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ApiTokenListResponse}
 * @response 404 {NotFoundErrorResponse} Sanctum не установлен.
 */
public function tokensList(Request $request): JsonResponse;
```

### `profile.tokenCreate`

```php
/**
 * Создать API-токен. Plain-text-токен возвращается ОДИН РАЗ — после ответа
 * восстановить нельзя.
 *
 * @input string $name Человекочитаемое имя.
 * @input array  ?$abilities Список разрешений (default ["*"]).
 * @input string ?$abilities[] Один из admin permissions либо "*".
 * @input integer ?$expires_in Секунд до expiration (null = бессрочный).
 *
 * @output object $payload
 * @output object $payload.token ApiToken meta.
 * @output integer $payload.token.id
 * @output string  $payload.token.name
 * @output array   $payload.token.abilities
 * @output string(date-time) ?$payload.token.expires_at
 * @output string  $payload.plain_text_token Показывается ОДИН раз.
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 201 {ApiTokenCreatedResponse}
 * @response 422 {ValidationErrorResponse}
 * @response 404 {NotFoundErrorResponse} Sanctum не установлен.
 */
public function tokenCreate(Request $request): JsonResponse;
```

Events: `Admin\Events\ApiTokenCreated` (audit).

### `profile.tokenRevoke`

```php
/**
 * Отозвать API-токен (удаление записи).
 *
 * @input integer $id ID токена.
 *
 * @output null $payload
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {SuccessResponse}
 * @response 404 {NotFoundErrorResponse} Токен не принадлежит юзеру.
 */
public function tokenRevoke(Request $request): JsonResponse;
```

Events: `Admin\Events\ApiTokenRevoked` (audit).

### `profile.tokenRegenerate`

```php
/**
 * Регенерировать токен — старый отзывается, новый создаётся с теми же abilities/expires.
 *
 * @input integer $id
 * @input string ?$password Re-auth.
 *
 * @output object $payload
 * @output object $payload.token Новый ApiToken.
 * @output string $payload.plain_text_token
 *
 * @security AdminSession
 * @security AdminBearer
 * @response 200 {ApiTokenCreatedResponse}
 * @response 404 {NotFoundErrorResponse}
 * @response 422 {ValidationErrorResponse}
 */
public function tokenRegenerate(Request $request): JsonResponse;
```
