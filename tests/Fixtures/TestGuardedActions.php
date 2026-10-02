<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Action\AsyncAction;
use Dskripchenko\LaravelAdmin\Action\BulkAction;
use Dskripchenko\LaravelAdmin\Action\Button;
use Dskripchenko\LaravelAdmin\Action\DropDown;
use Dskripchenko\LaravelAdmin\Action\ModalAction;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;
use Dskripchenko\LaravelAdmin\Table\TableColumn;

/**
 * Actions guarded by permission() and canSee(), for the enforcement tests.
 *
 * @internal
 */
final class TestGuardedActionResource extends Resource
{
    public static string $model = TestResourceUserModel::class;

    /** @var list<string> */
    public static array $calls = [];

    public static function slug(): string
    {
        return 'test-guarded';
    }

    public static function permission(): string
    {
        return 'admin.test-guarded';
    }

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function columns(): array
    {
        return [TableColumn::make('id')];
    }

    public function actions(): array
    {
        return [
            BulkAction::make('Open')->withName('open')->method('mark'),
            BulkAction::make('Guarded bulk')->withName('guarded-bulk')->method('mark')
                ->permission('admin.test-guarded.archive'),
            Button::make('Guarded row')->withName('guarded-row')->method('mark')
                ->position(['row'])->permission('admin.test-guarded.archive'),
            Button::make('Guarded standalone')->withName('guarded-standalone')->method('mark')
                ->position(['header'])->permission('admin.test-guarded.archive'),
            ModalAction::make('Guarded modal')->withName('guarded-modal')->method('mark')
                ->position(['row'])->permission('admin.test-guarded.archive')
                ->fields([Input::make('reason')]),
            Button::make('Hidden')->withName('hidden')->method('mark')
                ->position(['header'])->canSee(false),
            DropDown::make('More')->withName('more')->items([
                BulkAction::make('Nested open')->withName('nested-open')->method('mark'),
                BulkAction::make('Nested guarded')->withName('nested-guarded')->method('mark')
                    ->permission('admin.test-guarded.archive'),
            ]),
            DropDown::make('Locked')->withName('locked')->permission('admin.test-guarded.archive')->items([
                BulkAction::make('Inside locked')->withName('inside-locked')->method('mark'),
            ]),
            AsyncAction::make('Guarded async')->withName('guarded-async')
                ->handler(TestGuardedAsyncHandler::class, 'run')
                ->position(['header'])
                ->permission('admin.test-guarded.archive'),
        ];
    }

    /**
     * @param  list<int|string>  $ids
     * @param  array<string, mixed>  $payload
     */
    public function mark(array $ids, array $payload = []): int
    {
        self::$calls[] = 'mark';

        return count($ids);
    }
}

/**
 * @internal
 */
final class TestGuardedActionScreen extends Screen
{
    /** @var list<string> */
    public static array $calls = [];

    public function query(mixed ...$params): Repository|array
    {
        return [];
    }

    public function layout(): array
    {
        return [
            Rows::make([
                Button::make('Layout guarded')->method('layoutGuarded')->permission('admin.screen.layout'),
            ]),
        ];
    }

    public function commandBar(): array
    {
        return [
            Button::make('Open')->method('open'),
            Button::make('Guarded')->method('guarded')->permission('admin.screen.guarded'),
            Button::make('Hidden')->method('hidden')->canSee(false),
            ModalAction::make('Modal')->method('modal')->permission('admin.screen.guarded')
                ->fields([Input::make('reason')]),
            DropDown::make('More')->items([
                Button::make('Nested')->method('nested')->permission('admin.screen.guarded'),
            ]),
            AsyncAction::make('Screen async')->handler(TestGuardedAsyncHandler::class, 'screenRun')
                ->permission('admin.screen.guarded'),
        ];
    }

    /** @param array<string, mixed> $state */
    public function open(array $state = []): void
    {
        self::$calls[] = 'open';
    }

    /** @param array<string, mixed> $state */
    public function guarded(array $state = []): void
    {
        self::$calls[] = 'guarded';
    }

    /** @param array<string, mixed> $state */
    public function hidden(array $state = []): void
    {
        self::$calls[] = 'hidden';
    }

    /** @param array<string, mixed> $state */
    public function modal(array $state = []): void
    {
        self::$calls[] = 'modal';
    }

    /** @param array<string, mixed> $state */
    public function nested(array $state = []): void
    {
        self::$calls[] = 'nested';
    }

    /** @param array<string, mixed> $state */
    public function layoutGuarded(array $state = []): void
    {
        self::$calls[] = 'layoutGuarded';
    }

    /** No button names it: the screen's own permission is the guard. */
    public function unbound(array $state = []): void
    {
        self::$calls[] = 'unbound';
    }
}

/**
 * @internal
 */
final class TestGuardedAsyncHandler
{
    /** @return array<string, bool> */
    public function run(): array
    {
        return ['ok' => true];
    }

    /** @return array<string, bool> */
    public function screenRun(): array
    {
        return ['ok' => true];
    }

    /** @return array<string, bool> */
    public function allowlistGuarded(): array
    {
        return ['ok' => true];
    }

    /** @return array<string, bool> */
    public function free(): array
    {
        return ['ok' => true];
    }
}
