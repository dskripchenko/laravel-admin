<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Layout\Rows;
use Dskripchenko\LaravelAdmin\Resource\Resource;

/**
 * `formLayout($context)` наконец что-то значит.
 *
 * Параметр объявлен с самого начала, но ядро звало метод ВСЕГДА с `'update'`:
 * форма создания получала раскладку правки со всеми её вкладками, включая те,
 * которым до сохранения записи нечего показать. Пустая вкладка выглядит
 * поломкой, хотя показывать ей просто нечего.
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

        // На создании второго поля нет — ему нечего показать до сохранения.
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
    // Манифест едет каждым бутстрапом: лишнее дерево полей у ресурсов, которым
    // это не нужно, — вес на каждой загрузке панели.
    expect((new SameCtxResource)->meta()['create_fields'])->toBeNull();
});
