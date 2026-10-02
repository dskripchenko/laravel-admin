<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Auth;

use Dskripchenko\LaravelAdmin\Panel\Panel;
use Dskripchenko\LaravelAdmin\Panel\Panels;
use Dskripchenko\LaravelAdmin\Permission\Concerns\HasAdminAccess;
use Illuminate\Database\Eloquent\Model;

/**
 * Whether an authenticated user may enter a panel at all.
 *
 * In the `dedicated` strategy every row of the panel's own user table is an
 * administrator by definition, so nobody is turned away here. In the `shared`
 * strategy the users are the host's own — customers, subscribers — and being
 * able to log in to the site must not mean being able to open the admin: a
 * user gets in only with at least one admin role.
 *
 * A model decides for itself by defining `canAccessAdmin(string $panelId):
 * bool`; that answer wins in either strategy.
 *
 * Checked at the login, in AuthController, on every API request, in
 * AdminAuth, and when the shell renders, in BootstrapBuilder — so that the
 * site's session of a plain user does not show up as a logged-in admin.
 */
final class PanelAccess
{
    public static function allows(object $user, ?Panel $panel = null): bool
    {
        $panel ??= Panels::current();

        if (method_exists($user, 'canAccessAdmin')) {
            return $user->canAccessAdmin($panel->id) === true;
        }

        if ((string) ($panel->auth['strategy'] ?? 'dedicated') !== 'shared') {
            return true;
        }

        if ($user instanceof Model && method_exists($user, 'roles')
            && in_array(HasAdminAccess::class, class_uses_recursive($user), true)) {
            return $user->roles()->exists();
        }

        // A model with an authorization contract of its own — a public
        // hasAccess() and nothing else — keeps answering through it.
        return true;
    }
}
