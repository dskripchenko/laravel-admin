<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\BulkAction;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * A resource for testing the /{slug}/action endpoint.
 *
 * It declares the bulk actions 'archive' and 'publish', which delegate through
 * methodName to the resource ($this->archive($ids) / $this->publish($ids)).
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
            BulkAction::make('Проверить связь')
                ->method('checkLink')
                ->withName('check-link'),
            BulkAction::make('Сломаться')
                ->method('explode')
                ->withName('explode'),
        ];
    }

    /** A helper method for the archive bulk action: it marks status='archived'. */
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

    /** A refusal on the merits: the action ran and explains why it did not work out. */
    public function checkLink(array $ids, array $payload = []): int
    {
        throw new Dskripchenko\LaravelAdmin\Resource\ActionFailedException(
            'Не удалось подключиться: хост не отвечает',
        );
    }

    /** A real breakage — it must stay a 500. */
    public function explode(array $ids, array $payload = []): int
    {
        throw new LogicException('внутренняя ошибка');
    }
}
