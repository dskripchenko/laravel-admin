<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Slug;

/**
 * Slug::generate() and the SPA's slugify() are checked against the same cases
 * (resources/ts/__fixtures__/slug-cases.json; the TS side is
 * SlugField.test.ts), so the form suggests the slug the server would produce.
 */
dataset('shared slug cases', function (): array {
    $json = json_decode(
        (string) file_get_contents(__DIR__.'/../../resources/ts/__fixtures__/slug-cases.json'),
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $cases = [];
    foreach ($json['cases'] as [$source, $separator, $expected]) {
        $cases[json_encode([$source, $separator], JSON_UNESCAPED_UNICODE)] = [$source, $separator, $expected];
    }

    return $cases;
});

it('generates the shared slug cases', function (string $source, string $separator, string $expected): void {
    expect(Slug::generate($source, $separator))->toBe($expected);
})->with('shared slug cases');

it('serializes from, separator and follow apart from visibleWhen()', function (): void {
    $attributes = Slug::make('slug')
        ->from('title')
        ->separator('_')
        ->reactive(false)
        ->visibleWhen('kind', 'page')
        ->toArray()['attributes'];

    expect($attributes['from'])->toBe('title');
    expect($attributes['separator'])->toBe('_');
    expect($attributes['follow'])->toBeFalse();
    expect($attributes['reactive'])->toBe(['kind' => 'page']);
});
