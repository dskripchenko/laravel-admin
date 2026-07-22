<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Impersonation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Менеджер impersonation-сессии.
 *
 * Хранит ID оригинального админа в session под ключом `admin_impersonator_id`,
 * пока активна impersonation. start()/stop() переключают авторизованного
 * пользователя на admin-guard'е.
 *
 * Power-check: при `block_higher_powered=true` запрещает войти под юзером,
 * у которого больше permissions, чем у текущего (защита от privilege-escalation
 * через impersonation).
 */
final class ImpersonationManager
{
    public const SESSION_KEY = 'admin_impersonator_id';

    /**
     * Включена ли impersonation в конфиге.
     */
    public function enabled(): bool
    {
        return (bool) config('admin.auth.impersonation.enabled', false);
    }

    /**
     * Permission, которым нужно владеть, чтобы запускать impersonation.
     */
    public function requiredPermission(): string
    {
        return (string) config('admin.auth.impersonation.permission', 'admin.impersonate');
    }

    /**
     * Активна ли impersonation сейчас.
     */
    public function isActive(): bool
    {
        return Session::has(self::SESSION_KEY);
    }

    /**
     * ID оригинального (impersonator) пользователя.
     */
    public function impersonatorId(): int|string|null
    {
        /** @var int|string|null $id */
        $id = Session::get(self::SESSION_KEY);

        return $id;
    }

    /**
     * Запустить impersonation: залогинить под `$target`, сохранить ID оригинала.
     *
     * Гарантирует, что impersonator уже аутентифицирован.
     */
    public function start(Authenticatable&Model $impersonator, Authenticatable&Model $target): void
    {
        $guard = $this->guard();

        Session::put(self::SESSION_KEY, $impersonator->getKey());
        Auth::guard($guard)->login($target);
        // Сессия легитимно сменила юзера — обновляем hash для AdminAuth
        // (иначе session-invalidation счёл бы её устаревшей).
        Session::put('password_hash_'.$guard, (string) $target->getAuthPassword());
    }

    /**
     * Остановить impersonation, вернуть оригинала.
     *
     * Возвращает Authenticatable оригинала или null если оригинал не найден
     * (был удалён) — в этом случае сессию также очищаем, но логин не делаем.
     */
    public function stop(): ?Authenticatable
    {
        $impersonatorId = $this->impersonatorId();
        Session::forget(self::SESSION_KEY);

        if ($impersonatorId === null) {
            return null;
        }

        $provider = Auth::createUserProvider(
            \Dskripchenko\LaravelAdmin\Panel\Panels::currentProvider(),
        );
        $user = $provider?->retrieveById($impersonatorId);

        if ($user instanceof Authenticatable) {
            Auth::guard($this->guard())->login($user);
            Session::put('password_hash_'.$this->guard(), (string) $user->getAuthPassword());
        }

        return $user;
    }

    /**
     * Заблокировать impersonation целевого юзера, у которого больше прав,
     * чем у impersonator'а (если включено `block_higher_powered`).
     *
     * Сравнение по числу permissions: суррогатно, но защищает от обхода RBAC.
     * Wildcards (*, admin.users.*) интерпретируются буквально — кто их имеет,
     * считается «выше».
     */
    public function isHigherPowered(Authenticatable&Model $impersonator, Authenticatable&Model $target): bool
    {
        if (! (bool) config('admin.auth.impersonation.block_higher_powered', false)) {
            return false;
        }

        if (! method_exists($impersonator, 'getAllPermissions')
            || ! method_exists($target, 'getAllPermissions')) {
            return false;
        }

        $imp = (array) $impersonator->getAllPermissions();
        $tgt = (array) $target->getAllPermissions();

        // Если у impersonator'а есть `*` — он всегда выше или равен.
        if (in_array('*', $imp, true)) {
            return false;
        }

        // У target есть permissions, которых нет у impersonator'а?
        return count(array_diff($tgt, $imp)) > 0;
    }

    private function guard(): string
    {
        return \Dskripchenko\LaravelAdmin\Panel\Panels::currentGuard();
    }
}
