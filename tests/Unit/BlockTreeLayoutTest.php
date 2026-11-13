<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Textarea;
use Dskripchenko\LaravelAdmin\Layout\BlockTree;
use Dskripchenko\LaravelAdmin\Layout\BlockTree\BlockPreset;
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

it('answers a saved draft with the renamed refs and the fingerprint, not the tree', function (): void {
    $answer = BlockTree::draftSaved(['new-1a2b' => 51, 'new-3c4d' => 'block-52'], 'fp-7', '2026-11-13T12:00:00+00:00');

    expect(array_keys($answer['blockTree']))->toBe(['refs', 'fingerprint', 'savedAt'])
        ->and((array) $answer['blockTree']['refs'])->toBe(['new-1a2b' => 51, 'new-3c4d' => 'block-52'])
        ->and($answer['blockTree']['fingerprint'])->toBe('fp-7')
        ->and($answer['blockTree']['savedAt'])->toBe('2026-11-13T12:00:00+00:00')
        ->and($answer)->not->toHaveKey('message');

    // No new blocks: still an object in the JSON, and the time is now.
    $empty = BlockTree::draftSaved([], 'fp-8');
    expect(json_encode($empty['blockTree']['refs']))->toBe('{}')
        ->and($empty['blockTree']['savedAt'])->toBeString();
});

it('serializes the palette presets: type, data, meta, children, group and icon', function (): void {
    $array = blockTreeEditor()
        ->presets([
            BlockPreset::make('library-42', 'Footer (shared)', 'text')
                ->group('Shared blocks')
                ->icon('library')
                ->description('Edited in the library')
                ->thumbnail('/img/footer.png')
                ->data(['body' => '<p>©</p>'])
                ->meta(['library' => 42, 'fields' => ['body' => ['inherited' => true, 'base' => '<p>©</p>']]])
                ->children([['type' => 'button', 'data' => ['label' => 'Up']]]),
            ['key' => 'cta', 'label' => 'Call to action', 'type' => 'columns', 'children' => [['type' => 'text']]],
        ])
        ->preset('hero-plain', 'Plain hero', 'hero', ['title' => 'Hello'], ['accepts' => []], 'Ready', 'image')
        // A type this editor does not have: not offered.
        ->preset('ghost', 'Ghost', 'gallery')
        ->presets([['key' => 'ghost-child', 'label' => 'Ghost child', 'type' => 'columns', 'children' => [['type' => 'gallery']]]])
        ->toArray();

    expect(array_column($array['presets'], 'key'))->toBe(['library-42', 'cta', 'hero-plain'])
        ->and($array['presets'][0])->toMatchArray([
            'key' => 'library-42',
            'label' => 'Footer (shared)',
            'type' => 'text',
            'icon' => 'library',
            'description' => 'Edited in the library',
            'group' => 'Shared blocks',
            'thumbnail' => '/img/footer.png',
        ])
        ->and((array) $array['presets'][0]['data'])->toBe(['body' => '<p>©</p>'])
        ->and((array) $array['presets'][0]['meta'])->toBe(['library' => 42, 'fields' => ['body' => ['inherited' => true, 'base' => '<p>©</p>']]])
        ->and($array['presets'][0]['children'][0]['type'])->toBe('button')
        ->and((array) $array['presets'][0]['children'][0]['data'])->toBe(['label' => 'Up'])
        ->and($array['presets'][0]['children'][0]['children'])->toBe([])
        ->and($array['presets'][2])->toMatchArray(['type' => 'hero', 'group' => 'Ready', 'icon' => 'image'])
        ->and((array) $array['presets'][2]['meta'])->toBe(['accepts' => []]);

    // Empty data and meta are objects in the JSON.
    $json = json_decode((string) json_encode($array['presets'][1]), true);
    expect(json_encode($array['presets'][1]['data']))->toBe('{}')
        ->and($json['children'][0])->toBe(['type' => 'text', 'data' => [], 'meta' => [], 'children' => []]);

    // Without presets the list is empty.
    expect(blockTreeEditor()->toArray()['presets'])->toBe([]);
});

it('refuses a preset without a key, a type or a typed child', function (): void {
    expect(fn () => BlockPreset::make('', 'X', 'text'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BlockPreset::make('x', 'X', ' '))->toThrow(InvalidArgumentException::class)
        ->and(fn () => BlockPreset::make('x', 'X', 'text')->children([['data' => []]]))->toThrow(InvalidArgumentException::class);
});

it('answers a saved draft with the data and meta of some nodes', function (): void {
    $answer = BlockTree::draftSaved(
        ['new-1a2b' => 51],
        'fp-9',
        '2026-11-13T12:00:00+00:00',
        nodes: [
            51 => ['data' => ['image' => '/storage/blocks/1.png'], 'meta' => ['library' => 42]],
            3 => ['meta' => []],
            7 => [],
        ],
    );

    expect(array_keys($answer['blockTree']))->toBe(['refs', 'fingerprint', 'savedAt', 'nodes']);
    $json = json_decode((string) json_encode($answer['blockTree']['nodes']), true);
    expect($json)->toBe([
        '51' => ['data' => ['image' => '/storage/blocks/1.png'], 'meta' => ['library' => 42]],
        '3' => ['meta' => []],
    ])
        ->and(json_encode($answer['blockTree']['nodes']))->toContain('"3":{"meta":{}}');

    // No nodes: the answer is as before.
    expect(array_keys(BlockTree::draftSaved([], 'fp-10', nodes: [])['blockTree']))->toBe(['refs', 'fingerprint', 'savedAt'])
        ->and(fn () => BlockTree::draftSaved([], 'fp', nodes: [5 => 'x']))->toThrow(InvalidArgumentException::class);
});

it('serializes the editor the same way when the nodes carry node rules and field meta', function (): void {
    // The node meta lives in the state's value: the layout's own props do not change.
    expect(array_keys(blockTreeEditor()->toArray()))->not->toContain('meta')
        ->and(blockTreeEditor()->toArray()['types'][1]['accepts'])->toBe(['text']);
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

/* ---------------- the node rules: meta.accepts, meta.locked ---------------- */

it('takes the types allowed inside a node from its meta.accepts', function (): void {
    $tree = validTree();
    $tree[1]['meta'] = ['accepts' => []];
    $errors = blockTreeEditor()->validator()->errors($tree, data: false);
    expect(array_column($errors, 'path'))->toBe(['1.children.0']);

    // Wider than the type: a button straight in the columns.
    $tree = validTree();
    $tree[1]['meta'] = ['accepts' => ['text', 'button']];
    $tree[1]['children'][] = ['ref' => 11, 'type' => 'button', 'data' => [], 'children' => []];
    expect(blockTreeEditor()->validator()->errors($tree, data: false))->toBe([]);
});

it('reads the node rules from the original tree when it is given', function (): void {
    $original = validTree();
    $original[1]['meta'] = ['accepts' => []];
    $original[1]['children'] = [];

    // The request widens the rule back: the stored one counts.
    $tree = validTree();
    $tree[1]['meta'] = ['accepts' => ['text']];
    $errors = blockTreeEditor()->validator()->errors($tree, data: false, original: $original);
    expect(array_column($errors, 'path'))->toBe(['1.children.0']);
});

it('keeps a locked node of the original tree as it was', function (): void {
    $original = validTree();
    $original[1]['children'][0]['meta'] = ['locked' => true]; // the text, with the button inside
    $validator = blockTreeEditor()->validator();

    $same = validTree();
    expect($validator->errors($same, data: false, original: $original))->toBe([]);

    $changed = validTree();
    $changed[1]['children'][0]['data']['body'] = 'Mine';
    $changed[1]['children'][0]['children'] = [];
    $errors = $validator->errors($changed, data: false, original: $original);
    expect(array_column($errors, 'ref'))->toBe(['new-a1', 'new-a1'])
        ->and($errors[0]['message'])->toContain('Text');

    $moved = validTree();
    $text = array_pop($moved[1]['children']);
    $moved[] = ['ref' => 20, 'type' => 'columns', 'data' => [], 'children' => [$text]];
    expect(array_column($validator->errors($moved, data: false, original: $original), 'path'))->toBe(['2.children.0']);

    $removed = validTree();
    $removed[1]['children'] = [];
    expect($validator->errors($removed, data: false, original: $original))->toHaveCount(1);

    // Gone with its unlocked parent removed as a whole: no error of its own.
    $wholeParent = validTree();
    array_pop($wholeParent);
    expect($validator->errors($wholeParent, data: false, original: $original))->toBe([])
        ->and($validator->passes($same, data: false, original: $original))->toBeTrue();
});
