<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Восстановить soft-deleted запись через ResourceController.restore.
 *
 * @return Button
 */
final class RestoreAction
{
    /**
     * @param  string  $base  Permission-base ресурса (например 'admin.posts').
     */
    public static function for(string $base): Button
    {
        /** @var Button $action */
        $action = Button::make('Восстановить')
            ->withName('restore')
            ->method('restore')
            ->position(['row'])
            ->permission($base.'.restore');

        $action->icon('rotate-ccw');
        $action->color('green');

        return $action;
    }
}
