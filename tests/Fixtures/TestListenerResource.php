<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * A resource whose form has a listener with a resource-method handler.
 *
 * @internal
 */
final class TestListenerResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'listener-users';
    }

    public function fields(): array
    {
        return [
            Input::make('name'),
            Select::make('role'),
        ];
    }

    public function formLayout(string $context): array
    {
        return [
            Input::make('name'),
            Layout::listener(fn (array $state): array => [
                Select::make('role')->options($context === 'create' ? ['guest' => 'Guest'] : ['admin' => 'Admin', 'guest' => 'Guest']),
                Input::make('greeting')->title((string) ($state['greeting'] ?? '')),
            ])->listen('name')->handler('onNameChange')->withId('greeting'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public function onNameChange(array $state): array
    {
        return ['greeting' => 'Hello, '.($state['name'] ?? '')];
    }
}
