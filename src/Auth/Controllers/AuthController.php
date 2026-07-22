<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\Controllers;

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\RecoveryCodes;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TotpGenerator;
use Dskripchenko\LaravelAdmin\Impersonation\ImpersonationManager;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * `auth` controller — login, logout, password-reset, email-verification.
 *
 * 2FA-challenge / impersonation actions добавятся в P2.3 / P2.5 — каждый
 * в своём controller'е (отдельной логикой для security boundary'ев).
 *
 * См. docs/api/auth.md.
 */
final class AuthController extends ApiController
{
    /**
     * Аутентификация по email/паролю.
     *
     * При включённой и подтверждённой 2FA вместо логина возвращается
     * `two_factor_required` + `challenge_token` (Cache::5min) — следующий шаг:
     * `auth/twoFactorChallenge` или `auth/twoFactorRecovery`.
     *
     * @input string(email) $email
     * @input string $password
     * @input boolean ?$remember
     *
     * @output object $payload
     * @output object $payload.user
     * @output string $payload.redirect_url
     *
     * @security Public
     *
     * @response 200 {LoginResponse}
     * @response 200 {TwoFactorRequiredResponse}
     * @response 401 {InvalidCredentialsResponse}
     * @response 403 {AccountInactiveResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $remember = (bool) ($data['remember'] ?? false);

        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        // Resolve user without logging in — to check 2FA before establishing session.
        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveByCredentials($credentials);

        if (! $user instanceof Authenticatable
            || ! $provider->validateCredentials($user, $credentials)) {
            Event::dispatch(new Failed($guard, null, $credentials));

            return $this->error([
                'errorKey' => 'invalid_credentials',
                'message' => 'Неверный email или пароль',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! $user instanceof Model) {
            return $this->error([
                'errorKey' => 'invalid_credentials',
                'message' => 'Неверный email или пароль',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->getAttribute('is_active') === false) {
            return $this->error([
                'errorKey' => 'account_inactive',
                'message' => 'Учётная запись отключена',
            ], Response::HTTP_FORBIDDEN);
        }

        // 2FA gate: если включена, не логиним сразу — отдаём challenge_token.
        if (method_exists($user, 'hasTwoFactorEnabled') && $user->hasTwoFactorEnabled()) {
            $token = Str::random(64);
            Cache::put(
                "admin:2fa:challenge:{$token}",
                ['user_id' => $user->getKey(), 'remember' => $remember],
                now()->addMinutes(5),
            );

            return $this->error([
                'errorKey' => 'two_factor_required',
                'message' => 'Введите код из приложения-аутентификатора',
                'challenge_token' => $token,
            ], Response::HTTP_OK);
        }

        return $this->completeLogin($request, $user, $remember);
    }

    /**
     * Найти AdminUser по ID через guard provider — возвращает Model+Authenticatable.
     */
    private function resolveChallengeUser(int|string $userId): (Authenticatable&Model)|null
    {
        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveById($userId);

        return $user instanceof Authenticatable && $user instanceof Model ? $user : null;
    }

    /**
     * Сценарий после полной аутентификации (с 2FA или без): логинит, обновляет
     * last_login, регенерирует session, отдаёт user + redirect_url.
     */
    private function completeLogin(Request $request, Authenticatable&Model $user, bool $remember): JsonResponse
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();

        Auth::guard($guard)->login($user, $remember);

        // Панельные user-модели (v1.8) не обязаны иметь last_login-колонки —
        // пишем только когда они есть у таблицы.
        if (\Illuminate\Support\Facades\Schema::connection($user->getConnectionName())
            ->hasColumn($user->getTable(), 'last_login_at')) {
            $user->forceFill([
                'last_login_at' => now(),
                'last_login_ip' => $request->ip(),
            ])->save();
        }

        $request->session()->regenerate();

        return $this->success([
            'user' => $this->serializeUser($user),
            'permissions' => $this->resolveUserPermissions($user),
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
    }

    /**
     * Подтвердить TOTP-код после login, вернувшего two_factor_required.
     *
     * @input string $challenge_token
     * @input string $code 6-значный TOTP.
     *
     * @output object $payload
     *
     * @security Public
     *
     * @response 200 {LoginResponse}
     * @response 401 {InvalidTwoFactorResponse}
     */
    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $challenge = Cache::get("admin:2fa:challenge:{$data['challenge_token']}");
        if ($challenge === null) {
            return $this->error([
                'errorKey' => 'challenge_expired',
                'message' => 'Истёк срок challenge\'а, повторите вход',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->resolveChallengeUser($challenge['user_id']);

        if ($user === null
            || ! method_exists($user, 'hasTwoFactorEnabled')
            || ! $user->hasTwoFactorEnabled()) {
            return $this->error([
                'errorKey' => 'invalid_two_factor_code',
                'message' => 'Неверный код',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (! TotpGenerator::verify(
            (string) $user->getAttribute('two_factor_secret'),
            $data['code'],
            (int) config('admin.auth.two_factor.window', 1),
        )) {
            return $this->error([
                'errorKey' => 'invalid_two_factor_code',
                'message' => 'Неверный код',
            ], Response::HTTP_UNAUTHORIZED);
        }

        Cache::forget("admin:2fa:challenge:{$data['challenge_token']}");

        return $this->completeLogin($request, $user, (bool) ($challenge['remember'] ?? false));
    }

    /**
     * Использовать одноразовый recovery-код вместо TOTP.
     *
     * @input string $challenge_token
     * @input string $recovery_code
     *
     * @output object $payload
     *
     * @security Public
     *
     * @response 200 {RecoveryLoginResponse}
     * @response 401 {InvalidRecoveryCodeResponse}
     */
    public function twoFactorRecovery(Request $request): JsonResponse
    {
        $data = $request->validate([
            'challenge_token' => ['required', 'string'],
            'recovery_code' => ['required', 'string'],
        ]);

        $challenge = Cache::get("admin:2fa:challenge:{$data['challenge_token']}");
        if ($challenge === null) {
            return $this->error([
                'errorKey' => 'challenge_expired',
                'message' => 'Истёк срок challenge\'а',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = $this->resolveChallengeUser($challenge['user_id']);

        if ($user === null) {
            return $this->error([
                'errorKey' => 'invalid_recovery_code',
                'message' => 'Неверный recovery-код',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $existing = (array) $user->getAttribute('two_factor_recovery_codes');
        $remaining = RecoveryCodes::verify($existing, $data['recovery_code']);

        if ($remaining === null) {
            return $this->error([
                'errorKey' => 'invalid_recovery_code',
                'message' => 'Неверный recovery-код',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user->forceFill(['two_factor_recovery_codes' => $remaining])->save();
        Cache::forget("admin:2fa:challenge:{$data['challenge_token']}");

        $response = $this->completeLogin($request, $user, (bool) ($challenge['remember'] ?? false));

        // Добавляем recovery_codes_remaining в payload.
        /** @var array<string, mixed> $payload */
        $payload = (array) $response->getData(true)['payload'];
        $payload['recovery_codes_remaining'] = count($remaining);

        return $this->success($payload);
    }

    /**
     * Выйти из сессии.
     *
     * @output null $payload
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {SuccessResponse}
     */
    public function logout(Request $request): JsonResponse
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        Auth::guard($guard)->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return $this->success([]);
    }

    /**
     * Запросить отправку письма для сброса пароля.
     *
     * Ответ всегда успешный (даже если email не найден) — защита от user-enumeration.
     *
     * @input string(email) $email
     *
     * @output object $payload
     * @output string $payload.message
     *
     * @security Public
     *
     * @response 200 {GenericMessageResponse}
     * @response 422 {ValidationErrorResponse}
     * @response 429 {ThrottledResponse}
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $broker = \Dskripchenko\LaravelAdmin\Panel\Panels::currentPasswordBroker();
        Password::broker($broker)->sendResetLink($request->only('email'));

        return $this->success([
            'message' => 'Если такой email зарегистрирован, на него отправлено письмо со ссылкой для сброса пароля',
        ]);
    }

    /**
     * Сбросить пароль по токену из письма. При успехе — авто-логин.
     *
     * @input string(email) $email
     * @input string $token
     * @input string $password
     * @input string $password_confirmation
     *
     * @output object $payload
     * @output object $payload.user
     * @output string $payload.redirect_url
     *
     * @security Public
     *
     * @response 200 {LoginResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $broker = \Dskripchenko\LaravelAdmin\Panel\Panels::currentPasswordBroker();
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();

        $status = Password::broker($broker)->reset(
            $data,
            static function (CanResetPassword $user, string $password): void {
                if ($user instanceof Model) {
                    $user->forceFill([
                        'password' => Hash::make($password),
                        'remember_token' => Str::random(60),
                    ])->save();
                }

                if ($user instanceof Authenticatable) {
                    Event::dispatch(new PasswordReset($user));
                }
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => __($status),
                'messages' => ['token' => [__($status)]],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Авто-логин после успешного reset.
        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveByCredentials(['email' => $data['email']]);
        if ($user instanceof Authenticatable && $user instanceof Model) {
            Auth::guard($guard)->login($user);
        }

        return $this->success([
            'user' => $user instanceof Authenticatable && $user instanceof Model
                ? $this->serializeUser($user)
                : null,
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
    }

    /**
     * Подтвердить email по signed-параметрам из письма.
     *
     * @input integer $id
     * @input string $hash
     *
     * @output object $payload
     * @output string $payload.message
     * @output string $payload.redirect_url
     *
     * @security Public
     *
     * @response 200 {GenericMessageResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        if (! URL::hasValidSignature($request)) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Невалидная или просроченная ссылка верификации',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $modelClass = \Dskripchenko\LaravelAdmin\Panel\Panels::currentAuthModel();
        $user = $modelClass::find($request->input('id'));

        if (! $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail
            || ! hash_equals(
                sha1($user->getEmailForVerification()),
                (string) $request->input('hash'),
            )) {
            return $this->error([
                'errorKey' => 'validation',
                'message' => 'Невалидная подпись',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success([
                'message' => 'Email уже подтверждён',
                'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
            ]);
        }

        if ($user->markEmailAsVerified()) {
            Event::dispatch(new \Illuminate\Auth\Events\Verified($user));
        }

        return $this->success([
            'message' => 'Email подтверждён',
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
    }

    /**
     * Повторно отправить письмо для верификации email.
     *
     * @output object $payload
     * @output string $payload.message
     *
     * @security AdminSession
     * @security AdminBearer
     *
     * @response 200 {GenericMessageResponse}
     * @response 429 {ThrottledResponse}
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        if (! $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if ($user->hasVerifiedEmail()) {
            return $this->success(['message' => 'Email уже подтверждён']);
        }

        $user->sendEmailVerificationNotification();

        return $this->success(['message' => 'Письмо отправлено']);
    }

    /**
     * Сводный shape AdminUserSummary, который SPA получает в bootstrap/me/login.
     *
     * @param  Authenticatable&Model  $user
     * @return array<string, mixed>
     */
    private function serializeUser(Authenticatable $user): array
    {
        $twoFactorEnabled = method_exists($user, 'hasTwoFactorEnabled')
            && $user->hasTwoFactorEnabled();

        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'avatar' => $user->getAttribute('avatar'),
            'locale' => $user->getAttribute('locale') ?? config('admin.ui.default_locale', 'ru'),
            'theme' => $user->getAttribute('theme') ?? config('admin.ui.default_theme', 'light'),
            'twoFactorEnabled' => $twoFactorEnabled,
            'impersonator' => null,
        ];
    }

    /**
     * Плоский список permissions залогиненного user'а — для фронтового
     * AuthGuard'а после `auth/login` / `auth/2fa/verify`.
     *
     * @return list<string>
     */
    private function resolveUserPermissions(Authenticatable $user): array
    {
        return \Dskripchenko\LaravelAdmin\Permission\UserPermissions::resolve($user);
    }

    /**
     * Войти под другим админом.
     *
     * Требует permission `admin.impersonate`. Опция `block_higher_powered`
     * блокирует impersonate юзеров с большим набором прав.
     *
     * @input integer $user_id
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ImpersonationResponse}
     * @response 403 {ForbiddenErrorResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function startImpersonation(Request $request, ImpersonationManager $manager): JsonResponse
    {
        if (! $manager->enabled()) {
            return $this->error([
                'errorKey' => 'impersonation_disabled',
                'message' => 'Impersonation отключена в конфиге',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = $request->validate([
            'user_id' => ['required', 'integer'],
        ]);

        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $current = Auth::guard($guard)->user();

        if (! $current instanceof Authenticatable) {
            return $this->error([
                'errorKey' => 'unauthenticated',
                'message' => 'Unauthenticated',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Permission-check.
        if (! method_exists($current, 'hasAccess')
            || ! $current->hasAccess($manager->requiredPermission())) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => 'Нет права '.$manager->requiredPermission(),
            ], Response::HTTP_FORBIDDEN);
        }

        // Защита от вложенной impersonation.
        if ($manager->isActive()) {
            return $this->error([
                'errorKey' => 'already_impersonating',
                'message' => 'Сначала остановите текущую impersonation',
            ], Response::HTTP_FORBIDDEN);
        }

        $modelClass = \Dskripchenko\LaravelAdmin\Panel\Panels::currentAuthModel();
        $target = $modelClass::find($data['user_id']);

        if (! $target instanceof Authenticatable || ! $target instanceof Model) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Пользователь не найден',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($target->getKey() === $current->getKey()) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => 'Нельзя impersonate самого себя',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($manager->isHigherPowered($current, $target)) {
            return $this->error([
                'errorKey' => 'forbidden',
                'message' => 'Цель имеет больше прав, чем вы — impersonation запрещена',
            ], Response::HTTP_FORBIDDEN);
        }

        $manager->start($current, $target);

        return $this->success([
            'user' => $this->serializeUser($target),
            'impersonator' => [
                'id' => $current->getKey(),
                'name' => $current->getAttribute('name'),
            ],
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
    }

    /**
     * Завершить impersonation.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {LoginResponse}
     * @response 400 {NoActiveImpersonationResponse}
     */
    public function stopImpersonation(ImpersonationManager $manager): JsonResponse
    {
        if (! $manager->isActive()) {
            return $this->error([
                'errorKey' => 'no_active_impersonation',
                'message' => 'Нет активной impersonation',
            ], Response::HTTP_BAD_REQUEST);
        }

        $original = $manager->stop();

        if (! $original instanceof Authenticatable || ! $original instanceof Model) {
            return $this->error([
                'errorKey' => 'impersonator_not_found',
                'message' => 'Оригинальный пользователь не найден',
            ], Response::HTTP_BAD_REQUEST);
        }

        return $this->success([
            'user' => $this->serializeUser($original),
            'impersonator' => null,
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
    }
}
