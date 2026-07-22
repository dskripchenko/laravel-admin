<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Permission;

/**
 * Плоский список permissions пользователя для SPA (login payload / bootstrap).
 *
 * Панельные user-модели (v1.8 Panels, shared-strategy) обязаны реализовать
 * только контракт `hasAccess(string): bool` — перечислить их права нельзя.
 * Такие модели получают wildcard `['*']`: реальная авторизация остаётся за
 * backend'ом (AdminAccess → 403), а SPA-гарды не запирают пользователя в
 * /forbidden. Модели с granular-правами должны отдавать getAllPermissions().
 */
final class UserPermissions
{
    /**
     * @return list<string>
     */
    public static function resolve(?object $user): array
    {
        if ($user === null) {
            return [];
        }

        if (method_exists($user, 'getAllPermissions')) {
            return array_values((array) $user->getAllPermissions());
        }

        if (method_exists($user, 'hasAccess')) {
            return ['*'];
        }

        return [];
    }
}
