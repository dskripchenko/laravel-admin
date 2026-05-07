<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\BulkAction;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Resource для testing /{slug}/action endpoint'а.
 *
 * Объявляет BulkAction 'archive' и 'publish', которые делегируют
 * methodName на Resource ($this->archive($ids) / $this->publish($ids)).
 *
 * @internal
 */
final class TestActionResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    public static function slug(): string
    {
        return 'test-actions';
    }

    public static function permission(): string
    {
        return 'admin.test-actions';
    }

    public function fields(): array
    {
        return [
            Input::make('name')->required(),
        ];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id')->sort(),
            TableColumn::make('name')->sort(),
            TableColumn::make('status'),
        ];
    }

    public function actions(): array
    {
        return [
            BulkAction::make('Архивировать')
                ->method('archive')
                ->withName('archive'),
            BulkAction::make('Опубликовать')
                ->method('publish')
                ->withName('publish'),
        ];
    }

    /** Helper-method для BulkAction archive: помечает status='archived'. */
    public function archive(array $ids, array $payload = []): int
    {
        return TestResourceUserModel::query()
            ->whereIn('id', $ids)
            ->update(['status' => 'archived']);
    }

    public function publish(array $ids, array $payload = []): int
    {
        return TestResourceUserModel::query()
            ->whereIn('id', $ids)
            ->update(['status' => 'published']);
    }
}
