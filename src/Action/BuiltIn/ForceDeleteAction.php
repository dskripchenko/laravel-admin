<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Окончательное удаление soft-deleted записи через
 * ResourceController.forceDelete с обязательным confirm'ом.
 */
final class ForceDeleteAction
{
    public static function for(string $base): Button
    {
        /** @var Button $action */
        $action = Button::make('Удалить навсегда')
            ->withName('forceDelete')
            ->method('forceDelete')
            ->position(['row'])
            ->permission($base.'.force-delete')
            ->confirm([
                'title' => 'Окончательное удаление',
                'message' => 'Эту операцию невозможно отменить. Продолжить?',
            ]);

        $action->icon('trash-2');
        $action->color('red');
        $action->destructive(true);

        return $action;
    }
}
