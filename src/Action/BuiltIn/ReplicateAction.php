<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Action\BuiltIn;

use Dskripchenko\LaravelAdmin\Action\Button;

/**
 * Клонирование записи через ResourceController.replicate.
 */
final class ReplicateAction
{
    public static function for(string $base): Button
    {
        /** @var Button $action */
        $action = Button::make('Клонировать')
            ->withName('replicate')
            ->method('replicate')
            ->position(['row'])
            ->permission($base.'.replicate');

        $action->icon('copy');

        return $action;
    }
}
