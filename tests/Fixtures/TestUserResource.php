<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Demo Resource для unit/feature тестов.
 *
 * @internal
 */
final class TestUserResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Имя'),
            Input::make('email')->type('email')->required()->title('Email')
                ->rules(['unique:users,email']),
            Input::make('password')->onCreate()->onUpdate(false)->required()->title('Пароль'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')->sort()->search(),
            TableColumn::make('email')->copyable()->search(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('email'),
        ];
    }

    public function actions(): array
    {
        return [
            Button::make('Активировать')->method('activate'),
        ];
    }

    public function with(): array
    {
        return ['profile', 'roles'];
    }
}
