<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth\Controllers;

use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * При включённой 2FA — следующий action `twoFactorChallenge` (фаза P2.3).
     * На P2.2 — простой login без 2FA challenge'а.
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
     * @response 401 {InvalidCredentialsResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        $guard = (string) config('admin.auth.guard', 'admin');
        $remember = (bool) ($data['remember'] ?? false);

        $credentials = ['email' => $data['email'], 'password' => $data['password']];

        if (! Auth::guard($guard)->attempt($credentials, $remember)) {
            Event::dispatch(new Failed($guard, null, $credentials));

            return $this->error([
                'errorKey' => 'invalid_credentials',
                'message' => 'Неверный email или пароль',
            ], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Authenticatable&Model $user */
        $user = Auth::guard($guard)->user();

        // is_active — eloquent attribute, не PHP-property; используем getAttribute.
        if ($user->getAttribute('is_active') === false) {
            Auth::guard($guard)->logout();

            return $this->error([
                'errorKey' => 'account_inactive',
                'message' => 'Учётная запись отключена',
            ], Response::HTTP_FORBIDDEN);
        }

        // Touch last_login_*.
        $user->forceFill([
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ])->save();

        $request->session()->regenerate();

        Event::dispatch(new Login($guard, $user, $remember));

        return $this->success([
            'user' => $this->serializeUser($user),
            'redirect_url' => '/'.trim((string) config('admin.path', 'admin'), '/'),
        ]);
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
        $guard = (string) config('admin.auth.guard', 'admin');
        $user = Auth::guard($guard)->user();

        Auth::guard($guard)->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        if ($user !== null) {
            Event::dispatch(new Logout($guard, $user));
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

        $broker = (string) config('admin.auth.password_broker', 'admin_users');
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

        $broker = (string) config('admin.auth.password_broker', 'admin_users');
        $guard = (string) config('admin.auth.guard', 'admin');

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
            (string) config('admin.auth.provider', 'admin_users'),
        );
        $user = $provider?->retrieveByCredentials(['email' => $data['email']]);
        if ($user instanceof Authenticatable && $user instanceof Model) {
            Auth::guard($guard)->login($user);
            Event::dispatch(new Login($guard, $user, false));
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

        $modelClass = (string) config('admin.auth.model');
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
        $guard = (string) config('admin.auth.guard', 'admin');
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
        return [
            'id' => $user->getAuthIdentifier(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'avatar' => $user->getAttribute('avatar'),
            'locale' => $user->getAttribute('locale') ?? config('admin.ui.default_locale', 'ru'),
            'theme' => $user->getAttribute('theme') ?? config('admin.ui.default_theme', 'light'),
            'twoFactorEnabled' => false,                                  // P2.3
            'impersonator' => null,                                   // P2.5
        ];
    }
}
