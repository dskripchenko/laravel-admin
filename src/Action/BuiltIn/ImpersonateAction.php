<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Logs in as another administrator, through `auth.startImpersonation`.
 *
 * A row (and record) action of a users resource: the SPA posts the record's
 * key to `auth/startImpersonation` and reloads the panel as that user; the
 * shell's banner leads back. The permission defaults to
 * config('admin.auth.impersonation.permission'); the server checks it again,
 * along with `block_higher_powered`.
 */
final class ImpersonateAction
{
    public static function make(?string $permission = null): Button
    {
        /** @var Button $action */
        $action = Button::make('Войти как пользователь')
            ->withName('impersonate')
            ->method('startImpersonation')
            ->client('impersonate')
            ->position(['row', 'header'])
            ->permission($permission ?? (string) config('admin.auth.impersonation.permission', 'admin.impersonate'))
            ->confirm([
                'title' => 'Подмена пользователя',
                'message' => 'Вы войдёте под другим пользователем. Все действия будут аудированы.',
            ]);

        $action->icon('user-cog');

        return $action;
    }
}
