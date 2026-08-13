<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * `formLayout($context)` finally means something.
 *
 * The parameter was declared from the very beginning, but the core ALWAYS called
 * the method with `'update'`: the creation form received the edit layout with
 * all of its tabs, including those that have nothing to show before the row is
 * saved. An empty tab looks like a breakage even though it simply has nothing to
 * show.
 */
class CtxResource extends Resource
{
    public static function slug(): string
    {
        return 'ctx-probe';
    }

    public static function model(): string
    {
        return Dskripchenko\LaravelAdmin\Tests\Fixtures\Article::class;
    }

    public function fields(): array
    {
        return [Input::make('title')->title('Title'), Input::make('extra')->title('Extra')];
    }

    public function formLayout(string $context): array
    {
        $byName = [];
        foreach ($this->fields() as $f) {
            $byName[$f->name()] = $f;
        }

        // On creation the second field is absent — it has nothing to show before a save.
        return $context === 'create'
            ? [Rows::make([$byName['title']])]
            : [Rows::make([$byName['title'], $byName['extra']])];
    }
}

final class SameCtxResource extends CtxResource
{
    public static function slug(): string
    {
        return 'ctx-same';
    }

    public function formLayout(string $context): array
    {
        $byName = [];
        foreach ($this->fields() as $f) {
            $byName[$f->name()] = $f;
        }

        return [Rows::make([$byName['title'], $byName['extra']])];
    }
}

it('раскладка создания уезжает в манифест отдельным ключом', function (): void {
    $manifest = (new CtxResource)->meta();

    expect($manifest['create_fields'])->not->toBeNull()
        ->and($manifest['create_fields'])->not->toBe($manifest['fields']);

    $countRows = static fn (array $tree): int => count($tree[0]['items'] ?? []);

    expect($countRows($manifest['create_fields']))->toBe(1)
        ->and($countRows($manifest['fields']))->toBe(2);
});

it('одинаковые раскладки не возят второй копией', function (): void {
    // The manifest travels with every bootstrap: an extra field tree on the
    // resources that do not need one is weight on every load of the panel.
    expect((new SameCtxResource)->meta()['create_fields'])->toBeNull();
});
