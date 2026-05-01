<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdminStarter\Resources;

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Slug;
use Dskripchenko\LaravelAdmin\Field\Switcher;
use Dskripchenko\LaravelAdmin\Field\TagsInput;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Filter\InputFilter;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * RoleResource — CRUD над `admin_roles`.
 *
 * Permissions: admin.system.roles.{view,create,update,delete}.
 *
 * Системные роли (`is_system = true`, например Super Admin) защищены от
 * deletion в core'е (через Concerns\GuardsSystemRoles, отдельно от этого
 * Resource'а).
 */
final class RoleResource extends Resource
{
    public static string $model = Role::class;

    public static string $icon = 'shield';

    public static ?string $group = 'Системные';

    public static function slug(): string
    {
        return 'system-roles';
    }

    public static function permission(): string
    {
        return 'admin.system.roles';
    }

    public static function label(): string
    {
        return 'Роли';
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required()->title('Имя'),
            Slug::make('slug')->from('name')->required()->title('Slug'),
            Textarea::make('description')->title('Описание'),
            TagsInput::make('permissions')->title('Permission keys'),
            Switcher::make('is_system')->title('Системная роль (read-only после create)'),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort()->width('60px'),
            TableColumn::make('name')->sort()->search(),
            TableColumn::make('slug')->sort()->copyable(),
            TableColumn::make('is_system')->asBoolean('Системная', 'Пользовательская'),
            TableColumn::make('created_at')->sort()->asDateTime(),
        ];
    }

    public function filters(): array
    {
        return [
            InputFilter::for('slug')->label('Slug'),
        ];
    }
}
