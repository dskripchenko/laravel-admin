<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Code;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Markdown;

it('serializes a markdown layout with its options', function (): void {
    $array = Layout::markdown("# Title\n\nText")
        ->toc(depth: 4)
        ->tocLabel('Contents')
        ->linkBase('/admin/s/docs/')
        ->imageBase('/docs-assets/')
        ->card()
        ->toArray();

    expect(Layout::markdown('x'))->toBeInstanceOf(Markdown::class)
        ->and($array['kind'])->toBe('layout')
        ->and($array['type'])->toBe('markdown')
        ->and($array['markdown'])->toBe("# Title\n\nText")
        ->and($array['toc'])->toBeTrue()
        ->and($array['tocDepth'])->toBe(4)
        ->and($array['tocLabel'])->toBe('Contents')
        ->and($array['linkBase'])->toBe('/admin/s/docs/')
        ->and($array['stripMdExtension'])->toBeTrue()
        ->and($array['imageBase'])->toBe('/docs-assets/')
        ->and($array['card'])->toBeTrue()
        // The text travels once, at the top level.
        ->and($array['props'])->not->toHaveKey('markdown');
});

it('resolves a callable markdown source at serialization', function (): void {
    $calls = 0;
    $layout = Layout::markdown(function () use (&$calls): string {
        $calls++;

        return '**lazy**';
    });

    expect($calls)->toBe(0)
        ->and($layout->toArray()['markdown'])->toBe('**lazy**')
        ->and($calls)->toBe(1);
});

it('clamps the toc depth', function (): void {
    expect(Layout::markdown('')->toc(depth: 9)->toArray()['tocDepth'])->toBe(6)
        ->and(Layout::markdown('')->toc(depth: 1)->toArray()['tocDepth'])->toBe(2);
});

it('serializes a code layout', function (): void {
    $array = Layout::code('<?php echo 1;', 'php')
        ->title('index.php')
        ->lineNumbers()
        ->maxHeight(300)
        ->wrap()
        ->toArray();

    expect(Layout::code('x'))->toBeInstanceOf(Code::class)
        ->and($array['type'])->toBe('code')
        ->and($array['code'])->toBe('<?php echo 1;')
        ->and($array['language'])->toBe('php')
        ->and($array['title'])->toBe('index.php')
        ->and($array['lineNumbers'])->toBeTrue()
        ->and($array['maxHeight'])->toBe('300px')
        ->and($array['wrap'])->toBeTrue()
        ->and($array['props'])->not->toHaveKey('code');

    expect(Layout::code('SELECT 1', 'sql')->maxHeight('50vh')->toArray())
        ->toMatchArray(['language' => 'sql', 'maxHeight' => '50vh']);
});
