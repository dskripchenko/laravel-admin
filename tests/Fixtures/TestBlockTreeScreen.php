<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\BlockTree;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\BlockType;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Screen\Screen;
use Dskripchenko\LaravelAdmin\Support\Repository;

/**
 * A page builder screen for the block tree tests: the published tree and a
 * draft kept in static properties, the fingerprint checked on publish, the
 * tree validated by the layout's own validator.
 *
 * @internal
 */
final class TestBlockTreeScreen extends Screen
{
    /** @var list<array<string, mixed>> */
    public static array $published = [];

    /** @var list<array<string, mixed>>|null */
    public static ?array $draft = null;

    /** @var list<string> */
    public static array $calls = [];

    public static function reset(): void
    {
        self::$published = [
            ['ref' => 1, 'type' => 'hero', 'data' => ['title' => 'Welcome'], 'children' => []],
            ['ref' => 2, 'type' => 'text', 'data' => ['body' => 'Hello'], 'children' => []],
        ];
        self::$draft = null;
        self::$calls = [];
    }

    public static function fingerprint(array $nodes): string
    {
        return hash('sha256', (string) json_encode($nodes));
    }

    public function name(): string
    {
        return 'Page content';
    }

    public function query(mixed ...$params): Repository|array
    {
        return ['content' => $this->value()];
    }

    public function layout(): array
    {
        return [$this->editor()];
    }

    public function saveDraft(array $state): array
    {
        self::$calls[] = 'saveDraft';
        $nodes = $state['content']['nodes'] ?? [];
        $this->editor()->validator()->validate($nodes, 'content.nodes', data: false);
        self::$draft = $nodes;

        return BlockTree::saved();
    }

    public function publish(array $state): array
    {
        self::$calls[] = 'publish';
        $nodes = $state['content']['nodes'] ?? [];
        if (($state['content']['fingerprint'] ?? null) !== self::fingerprint(self::$published)) {
            return BlockTree::conflict('The page was changed by someone else', ['Hero: title'], self::fingerprint(self::$published));
        }
        $errors = $this->editor()->validator()->errors($nodes);
        if ($errors !== []) {
            return BlockTree::invalid($errors);
        }
        self::$published = $nodes;
        self::$draft = null;

        return BlockTree::saved($this->value(), 'Published');
    }

    public function discardDraft(array $state): array
    {
        self::$calls[] = 'discardDraft';
        self::$draft = null;

        return BlockTree::saved($this->value());
    }

    private function editor(): BlockTree
    {
        return Layout::blockTree('content')
            ->types([
                BlockType::make('hero', 'Hero')->root()->titleFrom('title')->fields([
                    Input::make('title')->title('Title')->rules(['required', 'max:40']),
                ]),
                BlockType::make('columns', 'Columns')->root()->accepts(['text']),
                BlockType::make('text', 'Text')->root()->titleFrom('body')->fields([
                    Textarea::make('body')->title('Body'),
                ]),
            ])
            ->preview('/test-preview')
            ->draft('saveDraft', permission: 'admin.pages.edit')
            ->publish('publish', permission: 'admin.pages.publish')
            ->discard('discardDraft', permission: 'admin.pages.edit')
            ->maxDepth(3);
    }

    /** @return array<string, mixed> */
    private function value(): array
    {
        return [
            'nodes' => self::$draft ?? self::$published,
            'published' => self::$draft === null ? null : self::$published,
            'fingerprint' => self::fingerprint(self::$published),
        ];
    }
}
