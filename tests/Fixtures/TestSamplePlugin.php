<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\AdminPlugin;

/**
 * Простой test-plugin: регистрирует TestUserResource при boot.
 *
 * @internal
 */
final class TestSamplePlugin implements AdminPlugin
{
    public bool $registered = false;

    public bool $booted = false;

    public function name(): string
    {
        return 'sample-plugin';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(Admin $admin): void
    {
        $this->booted = true;
        $admin->resources([TestUserResource::class]);
    }
}

/**
 * Второй plugin для теста дубликата имён.
 *
 * @internal
 */
final class TestDuplicatePlugin implements AdminPlugin
{
    public function name(): string
    {
        return 'sample-plugin'; // тот же name
    }

    public function version(): string
    {
        return '2.0.0';
    }

    public function register(): void {}

    public function boot(Admin $admin): void {}
}
