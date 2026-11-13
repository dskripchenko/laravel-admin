<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Layout;

/*
 * Several live lists on one screen: each list's URL namespace and the way its
 * rows open; tabs kept in the address and laid out in a column; a resource
 * that opens its rows in a drawer.
 */

it('ResourceIndex::queryKey() serializes the URL namespace of the list', function (): void {
    $arr = Layout::resourceIndex(TestTaskResource::class)->queryKey('tasks')->toArray();

    expect($arr['queryKey'])->toBe('tasks')
        ->and($arr['props']['queryKey'])->toBe('tasks')
        // Without one the list keeps the plain keys, as before.
        ->and(Layout::resourceIndex(TestTaskResource::class)->toArray())->not->toHaveKey('queryKey');
});

it('ResourceIndex::queryKey() rejects a key that does not fit a URL', function (string $key): void {
    Layout::resourceIndex(TestTaskResource::class)->queryKey($key);
})->with(['', '1tasks', 'my tasks', 'a.b', 'a[b]'])->throws(InvalidArgumentException::class);

it('ResourceIndex::rowOpen() serializes the drawer mode and its size', function (): void {
    $arr = Layout::resourceIndex(TestTaskResource::class)->rowOpen('drawer', 'xl')->toArray();
    expect($arr['rowOpen'])->toBe('drawer')
        ->and($arr['drawerSize'])->toBe('xl');

    $page = Layout::resourceIndex(TestTaskResource::class)->rowOpen('page')->toArray();
    expect($page['rowOpen'])->toBe('page')
        ->and($page)->not->toHaveKey('drawerSize');

    expect(Layout::resourceIndex(TestTaskResource::class)->toArray())->not->toHaveKey('rowOpen');
});

it('ResourceIndex::rowOpen() rejects an unknown mode', function (): void {
    Layout::resourceIndex(TestTaskResource::class)->rowOpen('modal');
})->throws(InvalidArgumentException::class);

it('ResourceIndex::nextAfterAction() moves on after an action and implies the drawer', function (): void {
    $arr = Layout::resourceIndex(TestTaskResource::class)->nextAfterAction()->toArray();
    expect($arr['nextAfterAction'])->toBeTrue()
        ->and($arr['rowOpen'])->toBe('drawer');

    // An explicit mode is kept.
    $page = Layout::resourceIndex(TestTaskResource::class)->rowOpen('page')->nextAfterAction()->toArray();
    expect($page['rowOpen'])->toBe('page');
});

it('Resource::rowOpen() reaches the manifest features, page by default', function (): void {
    expect((new TestTaskResource)->meta()['features']['rowOpen'])->toBe('page');

    $drawer = new class extends TestTaskResource
    {
        public function rowOpen(): string
        {
            return 'drawer';
        }
    };
    expect($drawer->meta()['features']['rowOpen'])->toBe('drawer');
});

it('Tabs::persistInQuery() names every tab by its label, made URL-safe, unless keys() names them', function (): void {
    $arr = Layout::tabs([
        'Договоры' => Layout::rows([Layout::markdown('a')]),
        'Котировки' => [],
        'Quotes' => [],
        'quotes' => [],
        '***' => [],
    ])->persistInQuery()->toArray();

    expect($arr['queryKey'])->toBe('tab')
        ->and(array_column($arr['items'], 'key'))->toBe(['dogovory', 'kotirovki', 'quotes', 'quotes-2', 'tab-5']);

    $named = Layout::tabs(['Договоры' => [], 'Котировки' => []])
        ->persistInQuery('product')
        ->keys(['contracts', 'quotes'])
        ->toArray();
    expect($named['queryKey'])->toBe('product')
        ->and(array_column($named['items'], 'key'))->toBe(['contracts', 'quotes']);
});

it('a tab the user may not see is left out with its label and key, the others keep theirs', function (): void {
    // No user: the lists' view permission is not granted.
    $arr = Layout::tabs([
        'Договоры' => Layout::resourceIndex(TestTaskResource::class)->queryKey('contracts'),
        'Справка' => Layout::rows([Layout::markdown('a')]),
        'Котировки' => Layout::rows([Layout::markdown('b')])->canSee(true),
    ])->persistInQuery()->toArray();

    expect(array_column($arr['items'], 'label'))->toBe(['Справка', 'Котировки'])
        ->and(array_column($arr['items'], 'key'))->toBe(['spravka', 'kotirovki']);
});

it('Tabs without persistInQuery() or keys() keep their index, as before', function (): void {
    $arr = Layout::tabs(['One' => [], 'Two' => []])->defaultTab(1)->toArray();

    expect($arr)->not->toHaveKey('queryKey')
        ->and($arr['items'][0])->not->toHaveKey('key')
        ->and($arr['default'])->toBe(1);
});

it('Tabs::persistInQuery() rejects a key that does not fit a URL', function (): void {
    Layout::tabs(['One' => []])->persistInQuery('my tab');
})->throws(InvalidArgumentException::class);

it('Tabs::vertical() lays the tabs out in a column', function (): void {
    expect(Layout::tabs(['One' => []])->vertical()->toArray()['orientation'])->toBe('vertical')
        ->and(Layout::tabs(['One' => []])->toArray())->not->toHaveKey('orientation')
        ->and(Layout::tabs(['One' => []])->vertical()->vertical(false)->toArray())->not->toHaveKey('orientation');
});
