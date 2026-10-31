<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Resource\ActionFailedException;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * A screen of documents for the document layout tests: a kanban board saved
 * and locked through screen methods, a diagram read from the state, and a
 * board the user cannot see.
 *
 * @internal
 */
final class TestDocumentScreen extends Screen
{
    /** The markup the board was saved with, last first. */
    public static ?string $saved = null;

    public static ?bool $lockedTo = null;

    /** @var list<string> */
    public static array $calls = [];

    public function name(): string
    {
        return 'Documents';
    }

    public function query(mixed ...$params): Repository|array
    {
        return [
            'board' => "## To do\n- Write docs\n## Done\n",
            'board_locked' => false,
            'schema' => "flowchart LR\n  A --> B",
        ];
    }

    public function layout(): array
    {
        return [
            Layout::kanban('board')
                ->title('Tasks')
                ->editable('saveBoard', permission: 'admin.board.edit')
                ->lockable('lockBoard', permission: 'admin.board.lock')
                ->locked('board_locked'),
            Layout::diagram('schema')->syntax('mermaid'),
            Layout::kanban('board')
                ->editable('saveSecret')
                ->canSee(fn (): bool => false),
        ];
    }

    public function saveBoard(string $markup): void
    {
        self::$calls[] = 'saveBoard';
        if (str_contains($markup, 'forbidden')) {
            throw new ActionFailedException('This board cannot hold that card');
        }
        self::$saved = $markup;
    }

    public function lockBoard(bool $locked): void
    {
        self::$calls[] = 'lockBoard';
        self::$lockedTo = $locked;
    }

    public function saveSecret(string $markup): void
    {
        self::$calls[] = 'saveSecret';
    }
}
