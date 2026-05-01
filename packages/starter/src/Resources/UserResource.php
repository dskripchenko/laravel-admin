<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Resources;

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Password;
use Dskripchenko\LaravelAdmin\Field\Select;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Filter\OptionsFilter;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * UserResource — CRUD над таблицей `admin_users` (core's AdminUser).
 *
 * Permissions: admin.system.users.{view,create,update,delete}.
 *
 * Поля в form: name / email / password (только при create) / locale /
 * theme / is_active.
 */
final class UserResource extends Resource
{
    public static string $model = AdminUser::class;

    public static string $icon = 'users';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-users';
    }

    public static function permission(): string
    {
        return 'admin.system.users';
    }

    public static function label(): string
    {
        return 'Пользователи';
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Имя'),
            Input::make('email')->required()->title('Email'),
            Password::make('password')->onCreate()->onUpdate(false)->required()->title('Пароль'),
            Select::make('locale')->options([
                'ru' => 'Русский',
                'en' => 'English',
            ])->title('Локаль'),
            Select::make('theme')->options([
                'light' => 'Светлая',
                'dark' => 'Тёмная',
                'system' => 'Как в системе',
            ])->title('Тема'),
            Switcher::make('is_active')->title('Активен'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('name')->sort()->search(),
            TableColumn::make('email')->sort()->search()->copyable(),
            TableColumn::make('locale')->asBadge([
                'ru' => 'default',
                'en' => 'info',
            ]),
            TableColumn::make('is_active')->asBoolean('Активен', 'Заблокирован'),
            TableColumn::make('created_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('email')->label('Email'),
            OptionsFilter::for('is_active')->label('Статус')->options([
                '1' => 'Активные',
                '0' => 'Заблокированные',
            ]),
            OptionsFilter::for('locale')->label('Локаль')->options([
                'ru' => 'Русский',
                'en' => 'English',
            ]),
        ];
    }
}
