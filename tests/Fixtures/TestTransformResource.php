<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * Resource с transformRecord-override — распаковка virtual-полей (см. hook).
 *
 * @internal
 */
final class TestTransformResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'transforms';
    }

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function transformRecord(Model $record): array
    {
        return [...$record->toArray(), 'virtual_upper' => strtoupper((string) $record->getAttribute('name'))];
    }
}
