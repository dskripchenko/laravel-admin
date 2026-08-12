<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Logs in as another administrator, through `auth.startImpersonation`.
 *
 * It is used as a row action in the users resource. The permission defaults to
 * `admin.impersonate`; see config('admin.auth.impersonation.permission').
 */
final class ImpersonateAction
{
    public static function make(?string $permission = null): Button
    {
        /** @var Button $action */
        $action = Button::make('Войти как пользователь')
            ->withName('impersonate')
            ->method('startImpersonation')
            ->position(['row'])
            ->permission($permission ?? (string) config('admin.auth.impersonation.permission', 'admin.impersonate'))
            ->confirm([
                'title' => 'Подмена пользователя',
                'message' => 'Вы войдёте под другим пользователем. Все действия будут аудированы.',
            ]);

        $action->icon('user-cog');

        return $action;
    }
}
