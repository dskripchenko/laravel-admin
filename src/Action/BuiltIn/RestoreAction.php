<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Restores a soft-deleted record through ResourceController.restore.
 *
 * @return Button
 */
final class RestoreAction
{
    /**
     * @param  string  $base  The resource's permission base, 'admin.posts' for instance.
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
