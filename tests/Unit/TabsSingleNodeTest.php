<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Layout\Tabs;

// A tab given one node — a live list, nested tabs, a block — shows that node;
// it used to be replaced by the node's empty list of children.
it('keeps a single node as the tab item', function (): void {
    $tabs = Tabs::make([
        'Nested' => Tabs::make(['A' => [Layout::markdown('a')]]),
        'Block' => Layout::block('Title', [Layout::markdown('b')]),
        'List' => [Layout::markdown('c'), Layout::markdown('d')],
    ])->toArray();

    expect($tabs['items'][0]['items'])->toHaveCount(1)
        ->and($tabs['items'][0]['items'][0]['type'])->toBe('tabs')
        ->and($tabs['items'][1]['items'][0]['type'])->toBe('block')
        ->and($tabs['items'][2]['items'])->toHaveCount(2);
});
