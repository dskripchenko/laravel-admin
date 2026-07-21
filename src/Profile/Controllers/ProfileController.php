<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Profile\Controllers;

use Dskripchenko\LaravelAdmin\Auth\TwoFactor\Base32;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\RecoveryCodes;
use Dskripchenko\LaravelAdmin\Auth\TwoFactor\TotpGenerator;
use Dskripchenko\LaravelApi\Controllers\ApiController;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * Профиль текущего администратора: данные, пароль, 2FA setup.
 *
 * См. контракт docs/api/profile.md. API-токены (Sanctum) реализуются в P15
 * — здесь не подключены, чтобы избежать жёсткой зависимости.
 */
class ProfileController extends ApiController
{
    /**
     * Получить профиль текущего пользователя.
     *
     * @output object $payload
     * @output object $payload.user
     * @output array $payload.available_locales
     * @output array $payload.available_themes
     * @output object $payload.two_factor
     * @output boolean $payload.api_tokens_enabled
     *
     * @security AdminSession
     *
     * @response 200 {ProfileResponse}
     * @response 401 {UnauthenticatedErrorResponse}
     */
    public function show(): JsonResponse
    {
        $user = $this->currentUser();

        return $this->success([
            'user' => $this->serializeUser($user),
            'available_locales' => (array) config('admin.ui.available_locales', []),
            'available_themes' => ['light', 'dark'],
            'two_factor' => $this->twoFactorState($user),
            'api_tokens_enabled' => false, // P15
        ]);
    }

    /**
     * Обновить профиль (имя, email, локаль, тема).
     *
     * @input string ?$name
     * @input string(email) ?$email
     * @input string ?$locale
     * @input string ?$theme
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ProfileUpdateResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function update(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $locales = (array) config('admin.ui.available_locales', []);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => [
                'sometimes',
                'email',
                Rule::unique((string) $user->getTable(), 'email')->ignore($user->getKey()),
            ],
            'locale' => ['sometimes', 'string', Rule::in($locales)],
            'theme' => ['sometimes', 'string', Rule::in(['light', 'dark'])],
        ]);

        // Email change → invalidate verification.
        if (array_key_exists('email', $data) && $data['email'] !== $user->getAttribute('email')) {
            $data['email_verified_at'] = null;
        }

        $user->forceFill($data)->save();

        return $this->success([
            'user' => $this->serializeUser($user->refresh()),
        ]);
    }

    /**
     * Сменить пароль с проверкой current_password.
     *
     * @input string $current_password
     * @input string $password
     * @input string $password_confirmation
     *
     * @output null $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->currentUser();

        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check((string) $request->input('current_password'), (string) $user->getAttribute('password'))) {
            return $this->error([
                'errorKey' => 'validation',
                'messages' => ['current_password' => ['Текущий пароль неверен']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill(['password' => $request->input('password')])->save();

        Event::dispatch(new PasswordReset($user));

        return $this->success([]);
    }

    /**
     * Получить статус 2FA.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {TwoFactorStatusResponse}
     */
    public function twoFactorStatus(): JsonResponse
    {
        $user = $this->currentUser();

        return $this->success($this->twoFactorState($user));
    }

    /**
     * Сгенерировать новый secret + recovery codes (state = pending).
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {TwoFactorSetupResponse}
     */
    public function twoFactorEnable(): JsonResponse
    {
        $user = $this->currentUser();

        $secret = Base32::generateSecret();
        $codeCount = (int) config('admin.auth.two_factor.recovery_codes', RecoveryCodes::DEFAULT_COUNT);
        $codes = RecoveryCodes::generate($codeCount);

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => null,
        ])->save();

        $issuer = (string) config('admin.brand.name', 'Admin');
        $accountName = (string) $user->getAttribute('email');

        return $this->success([
            'qr_code_svg' => null,
            'secret' => $secret,
            'qr_uri' => TotpGenerator::provisioningUri($secret, $accountName, $issuer),
            'recovery_codes' => $codes,
        ]);
    }

    /**
     * Подтвердить 2FA вводом TOTP.
     *
     * @input string $code
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {TwoFactorConfirmedResponse}
     * @response 422 {InvalidTwoFactorResponse}
     */
    public function twoFactorConfirm(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $request->validate(['code' => ['required', 'string']]);

        $secret = $user->getAttribute('two_factor_secret');
        if (! is_string($secret) || $secret === '') {
            return $this->error([
                'errorKey' => 'two_factor_not_initialised',
                'message' => 'Сначала вызовите twoFactorEnable',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $window = (int) config('admin.auth.two_factor.window', 1);
        if (! TotpGenerator::verify($secret, (string) $request->input('code'), $window)) {
            return $this->error([
                'errorKey' => 'invalid_two_factor_code',
                'message' => 'Неверный код',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $now = now();
        $user->forceFill(['two_factor_confirmed_at' => $now])->save();

        return $this->success([
            'enabled' => true,
            'confirmed_at' => $now->toIso8601String(),
        ]);
    }

    /**
     * Отключить 2FA с re-auth по паролю.
     *
     * @input string $password
     *
     * @output null $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function twoFactorDisable(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), (string) $user->getAttribute('password'))) {
            return $this->error([
                'errorKey' => 'validation',
                'messages' => ['password' => ['Неверный пароль']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        return $this->success([]);
    }

    /**
     * Регенерировать recovery codes (с re-auth).
     *
     * @input string $password
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {RecoveryCodesResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function twoFactorRegenerateCodes(Request $request): JsonResponse
    {
        $user = $this->currentUser();
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), (string) $user->getAttribute('password'))) {
            return $this->error([
                'errorKey' => 'validation',
                'messages' => ['password' => ['Неверный пароль']],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $codeCount = (int) config('admin.auth.two_factor.recovery_codes', RecoveryCodes::DEFAULT_COUNT);
        $codes = RecoveryCodes::generate($codeCount);

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        return $this->success(['recovery_codes' => $codes]);
    }

    /**
     * Список Sanctum-токенов текущего пользователя.
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ApiTokenListResponse}
     * @response 404 {NotFoundErrorResponse}  Sanctum не установлен.
     */
    public function tokensList(): JsonResponse
    {
        if (! $this->sanctumAvailable()) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'Sanctum is not installed',
            ], 404);
        }

        $user = $this->currentUser();
        if (! method_exists($user, 'tokens')) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'AdminUser does not have HasApiTokens trait',
            ], 404);
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, \Laravel\Sanctum\PersonalAccessToken> $tokens */
        $tokens = $user->{'tokens'}()->orderByDesc('created_at')->get();

        return $this->success([
            'data' => $tokens->map(static fn ($t): array => [
                'id' => $t->getKey(),
                'name' => $t->getAttribute('name'),
                'abilities' => $t->getAttribute('abilities') ?? [],
                'last_used_at' => $t->getAttribute('last_used_at')?->toIso8601String(),
                'expires_at' => $t->getAttribute('expires_at')?->toIso8601String(),
                'created_at' => $t->getAttribute('created_at')?->toIso8601String(),
            ])->all(),
        ]);
    }

    /**
     * Создать новый Sanctum-токен. Plain-text возвращается ОДИН раз.
     *
     * @input string $name
     * @input array ?$abilities
     * @input integer ?$expires_in_days
     *
     * @output object $payload
     *
     * @security AdminSession
     *
     * @response 200 {ApiTokenCreatedResponse}
     * @response 422 {ValidationErrorResponse}
     */
    public function tokenCreate(Request $request): JsonResponse
    {
        if (! $this->sanctumAvailable()) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'Sanctum is not installed',
            ], 404);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
            'abilities.*' => ['string'],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $user = $this->currentUser();
        if (! method_exists($user, 'createToken')) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'AdminUser does not have HasApiTokens trait',
            ], 404);
        }

        $abilities = (array) ($data['abilities'] ?? ['*']);
        $expires = isset($data['expires_in_days'])
            ? now()->addDays((int) $data['expires_in_days'])
            : null;

        /** @var \Laravel\Sanctum\NewAccessToken $newToken */
        $newToken = $user->createToken($data['name'], $abilities, $expires);

        return $this->success([
            'plain_text_token' => $newToken->plainTextToken,
            'token' => [
                'id' => $newToken->accessToken->getKey(),
                'name' => $newToken->accessToken->getAttribute('name'),
                'abilities' => $newToken->accessToken->getAttribute('abilities') ?? [],
                'expires_at' => $newToken->accessToken->getAttribute('expires_at')?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Удалить Sanctum-токен текущего пользователя.
     *
     * @input integer $id
     *
     * @output null $payload
     *
     * @security AdminSession
     *
     * @response 200 {SuccessResponse}
     * @response 404 {NotFoundErrorResponse}
     */
    public function tokenRevoke(Request $request): JsonResponse
    {
        if (! $this->sanctumAvailable()) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'Sanctum is not installed',
            ], 404);
        }

        $data = $request->validate(['id' => ['required', 'integer']]);
        $user = $this->currentUser();
        if (! method_exists($user, 'tokens')) {
            return $this->error([
                'errorKey' => 'sanctum_unavailable',
                'message' => 'AdminUser does not have HasApiTokens trait',
            ], 404);
        }

        $token = $user->{'tokens'}()->whereKey($data['id'])->first();
        if ($token === null) {
            return $this->error([
                'errorKey' => 'not_found',
                'message' => 'Token not found',
            ], 404);
        }
        $token->delete();

        return $this->success([]);
    }

    private function sanctumAvailable(): bool
    {
        return class_exists(\Laravel\Sanctum\Sanctum::class)
            && (bool) config('admin.auth.api_tokens.enabled', true);
    }

    /**
     * Текущий пользователь admin-guard. Гарантирован AdminAuth middleware.
     */
    private function currentUser(): Authenticatable&Model
    {
        $guard = \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
        $user = Auth::guard($guard)->user();

        if (! $user instanceof Model) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function twoFactorState(Authenticatable&Model $user): array
    {
        $confirmedAt = $user->getAttribute('two_factor_confirmed_at');
        $recoveryCodes = (array) $user->getAttribute('two_factor_recovery_codes');

        return [
            'enabled' => $user->getAttribute('two_factor_secret') !== null
                && $confirmedAt !== null,
            'confirmed_at' => $confirmedAt?->toIso8601String(),
            'recovery_codes_remaining' => count($recoveryCodes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(Authenticatable&Model $user): array
    {
        return [
            'id' => $user->getKey(),
            'name' => $user->getAttribute('name'),
            'email' => $user->getAttribute('email'),
            'locale' => $user->getAttribute('locale'),
            'theme' => $user->getAttribute('theme'),
            'is_active' => (bool) $user->getAttribute('is_active'),
            'email_verified_at' => $user->getAttribute('email_verified_at')?->toIso8601String(),
        ];
    }
}
