<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\BlockTree;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\BlockType;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Illuminate\Validation\ValidationException;

/**
 * Layout::blockTree: what it sends to the panel (the types with their fields,
 * the root types, the methods, the preview), the answers of the screen
 * methods, and the validator of a tree against the types.
 */
function blockTreeTypes(): array
{
    return [
        BlockType::make('hero', 'Hero')
            ->icon('image')
            ->root()
            ->titleFrom('title')
            ->description('A big heading')
            ->group('Top')
            ->thumbnail('/img/hero.png')
            ->fields([
                Input::make('title')->title('Title')->rules(['required', 'string', 'max:20']),
                Input::make('tone')->default('light'),
            ]),
        BlockType::make('columns', 'Columns')->root()->accepts(['text']),
        BlockType::make('text', 'Text')
            ->accepts(['button'])
            ->titleFrom(['body'])
            ->defaults(['body' => 'New text'])
            ->fields([Textarea::make('body')->title('Body')]),
        BlockType::make('button', 'Button')->rendersOnSite(false),
    ];
}

function blockTreeEditor(): BlockTree
{
    return Layout::blockTree('content')
        ->title('Content')
        ->types(blockTreeTypes())
        ->preview('/admin-builder/preview/page/3', ['mode' => 'page'])
        ->markerAttribute('data-block-ref')
        ->draft('saveDraft')
        ->publish('publish')
        ->discard('discardDraft')
        ->maxDepth(3)
        ->autosave(2000)
        ->previewDelay(400)
        ->height('calc(100vh - 200px)');
}

it('serializes the editor with its types, root types, methods and preview', function (): void {
    $array = blockTreeEditor()->toArray();

    expect($array['kind'])->toBe('layout')
        ->and($array['type'])->toBe('block_tree')
        ->and($array['source'])->toBe('content')
        ->and($array['title'])->toBe('Content')
        ->and($array['rootTypes'])->toBe(['hero', 'columns'])
        ->and($array['maxDepth'])->toBe(3)
        ->and($array['previewUrl'])->toBe('/admin-builder/preview/page/3')
        ->and((array) $array['previewParams'])->toBe(['mode' => 'page'])
        ->and($array['markerAttribute'])->toBe('data-block-ref')
        ->and($array['autosaveDelay'])->toBe(2000)
        ->and($array['previewDelay'])->toBe(400)
        ->and($array['height'])->toBe('calc(100vh - 200px)')
        ->and($array['draftMethod'])->toBe('saveDraft')
        ->and($array['publishMethod'])->toBe('publish')
        ->and($array['discardMethod'])->toBe('discardDraft')
        ->and($array['readOnly'])->toBeFalse()
        ->and(array_column($array['types'], 'type'))->toBe(['hero', 'columns', 'text', 'button']);

    $hero = $array['types'][0];
    expect($hero['label'])->toBe('Hero')
        ->and($hero['icon'])->toBe('image')
        ->and($hero['root'])->toBeTrue()
        ->and($hero['accepts'])->toBe([])
        ->and($hero['titleFrom'])->toBe(['title'])
        ->and($hero['description'])->toBe('A big heading')
        ->and($hero['group'])->toBe('Top')
        ->and($hero['thumbnail'])->toBe('/img/hero.png')
        ->and($hero['rendersOnSite'])->toBeTrue()
        ->and((array) $hero['defaults'])->toBe(['tone' => 'light'])
        ->and(array_column($hero['fields'], 'name'))->toBe(['title', 'tone'])
        ->and($hero['fields'][0]['kind'])->toBe('field')
        ->and($hero['fields'][0]['type'])->toBe('input')
        ->and($hero['fields'][0]['label'])->toBe('Title');

    expect($array['types'][2]['accepts'])->toBe(['button'])
        ->and((array) $array['types'][2]['defaults'])->toBe(['body' => 'New text'])
        ->and($array['types'][3]['rendersOnSite'])->toBeFalse();

    // The JSON the panel gets: objects stay objects.
    $json = json_decode(blockTreeEditor()->toJson(), true);
    expect($json['types'][1]['defaults'])->toBe([]);
});

it('takes the root types as given, and the types as arrays', function (): void {
    $array = Layout::blockTree('content')
        ->types([
            ['type' => 'a', 'label' => 'A', 'children' => ['b'], 'fields' => [Input::make('x'), ['kind' => 'field', 'type' => 'input', 'name' => 'y', 'rules' => ['required']]]],
            ['type' => 'b', 'label' => 'B', 'root' => true],
        ])
        ->rootTypes(['a'])
        ->toArray();

    expect($array['rootTypes'])->toBe(['a'])
        ->and($array['types'][0]['accepts'])->toBe(['b'])
        ->and(array_column($array['types'][0]['fields'], 'name'))->toBe(['x', 'y'])
        ->and($array['types'][1]['root'])->toBeTrue()
        ->and($array['previewUrl'])->toBeNull()
        ->and($array['markerAttribute'])->toBe('data-builder-ref')
        ->and($array['autosaveDelay'])->toBe(1500);
});

it('is read-only without the draft and publish methods', function (): void {
    $array = Layout::blockTree('content')->types(blockTreeTypes())->toArray();

    expect($array['readOnly'])->toBeTrue()
        ->and($array['draftMethod'])->toBeNull()
        ->and($array['publishMethod'])->toBeNull();
});

it('declares its methods for the server check', function (): void {
    $declared = blockTreeEditor()->publish('publish', permission: 'pages.publish')->childRenderables();

    expect($declared)->toHaveCount(3)
        ->and(array_map(fn ($b) => $b->toArray()['attributes']['method'] ?? null, $declared))
        ->toBe(['saveDraft', 'publish', 'discardDraft']);
});

it('rejects a bad source, preview, marker, depth and height', function (): void {
    expect(fn () => Layout::blockTree('not a key'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::blockTree('c')->preview('javascript:alert(1)'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::blockTree('c')->preview('//evil.test/x'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::blockTree('c')->markerAttribute('onclick'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::blockTree('c')->maxDepth(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::blockTree('c')->height('10px; color: red'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BlockType::make(''))->toThrow(InvalidArgumentException::class);
});

it('shapes the answers of the screen methods', function (): void {
    $saved = BlockTree::saved(['nodes' => []], 'Saved', '2026-11-13T12:00:00+00:00');
    expect($saved)->toBe([
        'blockTree' => ['value' => ['nodes' => []], 'savedAt' => '2026-11-13T12:00:00+00:00'],
        'message' => 'Saved',
    ]);

    expect(BlockTree::conflict('Changed', ['Hero: title'], 'fp-2'))->toBe([
        'blockTree' => ['conflict' => ['message' => 'Changed', 'differences' => ['Hero: title'], 'fingerprint' => 'fp-2']],
    ]);

    $invalid = BlockTree::invalid([['ref' => 1, 'path' => '0', 'field' => null, 'message' => 'x']]);
    expect($invalid['blockTree']['errors'])->toHaveCount(1)
        ->and($invalid['blockTree']['message'])->toBeString();
});

/* ---------------- the validator ---------------- */

function validTree(): array
{
    return [
        ['ref' => 1, 'type' => 'hero', 'data' => ['title' => 'Welcome'], 'children' => []],
        ['ref' => 2, 'type' => 'columns', 'data' => [], 'children' => [
            ['ref' => 'new-a1', 'type' => 'text', 'data' => ['body' => 'Hi'], 'children' => [
                ['ref' => 4, 'type' => 'button', 'data' => null, 'children' => []],
            ]],
        ]],
    ];
}

it('passes a tree within the rules', function (): void {
    expect(blockTreeEditor()->validator()->errors(validTree()))->toBe([])
        ->and(blockTreeEditor()->validator()->passes(validTree()))->toBeTrue();
});

it('reports unknown types, wrong nesting, depth, duplicate refs and bad shapes', function (): void {
    $tree = validTree();
    $tree[] = ['ref' => 5, 'type' => 'text', 'data' => [], 'children' => []];        // not a root type
    $tree[] = ['ref' => 6, 'type' => 'mystery', 'data' => [], 'children' => [         // unknown type
        ['ref' => 7, 'type' => 'button', 'data' => [], 'children' => []],             // not reported again
    ]];
    $tree[0]['children'][] = ['ref' => 8, 'type' => 'text', 'data' => [], 'children' => []]; // a hero takes none
    $tree[1]['children'][0]['children'][0]['children'][] = ['ref' => 9, 'type' => 'button', 'data' => [], 'children' => []]; // inside a button, and too deep
    $tree[] = ['ref' => 1, 'type' => 'hero', 'data' => ['title' => 'Again'], 'children' => []];   // duplicate ref
    $tree[] = ['type' => 'hero', 'data' => 'text', 'children' => 'none'];                         // no ref, data and children of a wrong kind
    $tree[] = 'not a node';

    $errors = blockTreeEditor()->validator()->errors($tree, data: false);
    $byPath = [];
    foreach ($errors as $e) {
        $byPath[$e['path']][] = $e['message'];
    }

    expect(array_map('strval', array_keys($byPath)))->toEqualCanonicalizing([
        '2', '3', '0.children.0', '1.children.0.children.0.children.0', '4', '5', '6',
    ])
        ->and($byPath['2'][0])->toContain('Text')
        ->and($byPath['3'][0])->toContain('mystery')
        ->and($byPath['0.children.0'][0])->toContain('Hero')
        ->and($byPath['1.children.0.children.0.children.0'])->toHaveCount(2)
        ->and($byPath['4'][0])->toContain('1')
        ->and($byPath['5'])->toHaveCount(3)
        ->and(collect($errors)->firstWhere('path', '0.children.0')['ref'])->toBe(8);

    expect(blockTreeEditor()->validator()->errors('nope'))->toHaveCount(1)
        ->and(blockTreeEditor()->validator()->errors(['a' => []]))->toHaveCount(1);
});

it('checks the data by the rules of the fields', function (): void {
    $tree = validTree();
    $tree[0]['data'] = ['title' => str_repeat('x', 30)];
    $tree[] = ['ref' => 10, 'type' => 'hero', 'data' => [], 'children' => []];

    $errors = blockTreeEditor()->validator()->errors($tree);

    expect($errors)->toHaveCount(2)
        ->and($errors[0])->toMatchArray(['ref' => 1, 'path' => '0', 'field' => 'title'])
        ->and($errors[1])->toMatchArray(['ref' => 10, 'path' => '2', 'field' => 'title'])
        ->and(blockTreeEditor()->validator()->errors($tree, data: false))->toBe([]);
});

it('throws a ValidationException keyed by the path', function (): void {
    $tree = validTree();
    $tree[1]['children'][0]['type'] = 'hero';

    try {
        blockTreeEditor()->validator()->validate($tree, 'content.nodes');
        $this->fail('No exception');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toContain('content.nodes.1.children.0');
    }

    $tree = validTree();
    $tree[0]['data']['title'] = '';
    try {
        blockTreeEditor()->validator()->validate($tree);
        $this->fail('No exception');
    } catch (ValidationException $e) {
        expect(array_keys($e->errors()))->toBe(['nodes.0.data.title']);
    }
});
