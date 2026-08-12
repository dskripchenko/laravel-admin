<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Plugin;

use Dskripchenko\LaravelAdmin\Admin;

/**
 * The contract of an admin plugin.
 *
 * Its lifecycle is register() → boot().
 *   - register() runs before the bindings are fully in place, which is where
 *     bindings are added and migrations loaded.
 *   - boot() runs afterwards, and that is where the resources, screens and
 *     permissions are registered through the $admin passed in.
 *
 * Plugins are declared in config/admin.php → plugins[], or through
 * Admin::plugins([...]).
 */
interface AdminPlugin
{
    /**
     * The plugin's unique identifier, used by discovery, dependencies and the audit.
     */
    public function name(): string;

    /**
     * The version, for compatibility.
     */
    public function version(): string;

    /**
     * The register stage: bindings and migrations. It has no access to the
     * Admin manager — that would be too early.
     */
    public function register(): void;

    /**
     * The boot stage: registering the resources, screens, permissions,
     * settings and widgets through the Admin passed in.
     */
    public function boot(Admin $admin): void;
}
