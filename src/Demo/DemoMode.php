<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Demo;

use Dskripchenko\LaravelAdmin\I18n\Localize;

/**
 * Demo mode, from `admin.demo`: the switches a public demonstration stand
 * needs — one-click sign-in as the demo accounts, and a read-only guard on the
 * operations that would spoil the stand for the next visitor (see
 * DemoReadonly).
 */
final class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('admin.demo.enabled', false);
    }

    public static function readonly(): bool
    {
        return self::enabled() && (bool) config('admin.demo.readonly', true);
    }

    /**
     * The demo accounts for the login page.
     *
     * @return list<array{label: string, email: string, password: string, description: string|null}>
     */
    public static function accounts(): array
    {
        $accounts = [];
        foreach ((array) config('admin.demo.accounts', []) as $account) {
            if (! is_array($account)) {
                continue;
            }
            $email = $account['email'] ?? null;
            $password = $account['password'] ?? null;
            if (! is_string($email) || $email === '' || ! is_string($password)) {
                continue;
            }
            $label = is_string($account['label'] ?? null) && $account['label'] !== '' ? $account['label'] : $email;
            $description = is_string($account['description'] ?? null) ? $account['description'] : null;

            $accounts[] = [
                'label' => (string) Localize::string($label),
                'email' => $email,
                'password' => $password,
                'description' => $description === null ? null : Localize::string($description),
            ];
        }

        return $accounts;
    }

    /**
     * The bootstrap's `demo` key: null when demo mode is off.
     *
     * @return array{readonly: bool, accounts: list<array{label: string, email: string, password: string, description: string|null}>}|null
     */
    public static function bootstrap(): ?array
    {
        if (! self::enabled()) {
            return null;
        }

        return [
            'readonly' => self::readonly(),
            'accounts' => self::accounts(),
        ];
    }
}
