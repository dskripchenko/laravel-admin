<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Layout\AsyncApi;
use Dskripchenko\LaravelAdmin\Layout\Bpmn;
use Dskripchenko\LaravelAdmin\Layout\Calendar;
use Dskripchenko\LaravelAdmin\Layout\Diagram;
use Dskripchenko\LaravelAdmin\Layout\Dmn;
use Dskripchenko\LaravelAdmin\Layout\Drawio;
use Dskripchenko\LaravelAdmin\Layout\Kanban;
use Dskripchenko\LaravelAdmin\Layout\Layout;

/* ---------------- the source, shared by every document layout ---------------- */

dataset('documents', [
    'diagram' => ['diagram', Diagram::class],
    'bpmn' => ['bpmn', Bpmn::class],
    'dmn' => ['dmn', Dmn::class],
    'drawio' => ['drawio', Drawio::class],
    'asyncapi' => ['asyncapi', AsyncApi::class],
    'kanban' => ['kanban', Kanban::class],
    'calendar' => ['calendar', Calendar::class],
]);

it('reads an identifier as a state key', function (string $type, string $class): void {
    $layout = Layout::{$type}('order.schema-v2');
    $array = $layout->toArray();

    expect($layout)->toBeInstanceOf($class)
        ->and($array['kind'])->toBe('layout')
        ->and($array['type'])->toBe($type)
        ->and($array['source'])->toBe('order.schema-v2')
        ->and($array['content'])->toBeNull()
        ->and($array['url'])->toBeNull()
        ->and($layout->source())->toBe('order.schema-v2');
})->with('documents');

it('takes anything else as the content', function (string $type): void {
    $content = "first line\nsecond: line";
    $array = Layout::{$type}($content)->toArray();

    expect($array['content'])->toBe($content)
        ->and($array['source'])->toBeNull()
        ->and($array['url'])->toBeNull();
})->with('documents');

it('loads the content from a URL, with a title and a height', function (string $type): void {
    $array = Layout::{$type}('x')
        ->url('/files/doc.txt')
        ->title('Document')
        ->height(560)
        ->toArray();

    expect($array['url'])->toBe('/files/doc.txt')
        ->and($array['source'])->toBeNull()
        ->and($array['content'])->toBeNull()
        ->and($array['title'])->toBe('Document')
        ->and($array['props']['title'])->toBe('Document')
        ->and($array['height'])->toBe('560px')
        ->and(Layout::{$type}('x')->height('70vh')->toArray()['height'])->toBe('70vh')
        ->and(Layout::{$type}('x')->url('https://admin.example.com/a.txt')->toArray()['url'])
        ->toBe('https://admin.example.com/a.txt');
})->with('documents');

it('rejects an empty source, a bad URL and a bad height', function (string $type): void {
    expect(fn () => Layout::{$type}(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('  '))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->url('javascript:alert(1)'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->url('//evil.test/a'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->height(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->height(-5))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->height('tall'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::{$type}('x')->height('10px; color: red'))->toThrow(InvalidArgumentException::class);
})->with('documents');

/* ---------------- per type ---------------- */

it('serializes the diagram syntax, and leaves it to detection when not set', function (): void {
    expect(Layout::diagram('erd')->syntax('dbml')->toArray()['syntax'])->toBe('dbml')
        ->and(Layout::diagram('erd')->toArray())->not->toHaveKey('syntax');

    foreach (Diagram::SYNTAXES as $syntax) {
        expect(Layout::diagram('x')->syntax($syntax)->toArray()['syntax'])->toBe($syntax);
    }
});

it('rejects an unknown diagram syntax', function (): void {
    expect(fn () => Layout::diagram('x')->syntax('graphviz'))->toThrow(InvalidArgumentException::class, 'Unknown diagram syntax')
        ->and(fn () => Layout::diagram('x')->syntax('Mermaid'))->toThrow(InvalidArgumentException::class);
});

it('serializes the bpmn highlight and the diagram', function (): void {
    $array = Layout::bpmn('process')->highlight(['Task_1', 'Gateway_2'])->showDiagram('Collaboration_1')->toArray();

    expect($array['highlight'])->toBe(['Task_1', 'Gateway_2'])
        ->and($array['diagram'])->toBe('Collaboration_1')
        ->and(Layout::bpmn('p')->showDiagram(1)->toArray()['diagram'])->toBe(1);
});

it('serializes the dmn highlight and the decision', function (): void {
    $array = Layout::dmn('rules')->highlight(['d' => 'Decision_1'])->decision('Decision_1')->toArray();

    expect($array['highlight'])->toBe(['Decision_1'])
        ->and($array['decision'])->toBe('Decision_1');
});

it('serializes the drawio page', function (): void {
    expect(Layout::drawio('net')->page('Rack B')->toArray()['page'])->toBe('Rack B')
        ->and(Layout::drawio('net')->page(2)->toArray()['page'])->toBe(2);
});

it('serializes an asyncapi document', function (): void {
    $array = Layout::asyncapi("asyncapi: 3.0.0\ninfo:\n  title: Events")->toArray();

    expect($array['type'])->toBe('asyncapi')
        ->and($array['content'])->toStartWith('asyncapi: 3.0.0');
});

it('serializes a read-only board by default', function (): void {
    $array = Layout::kanban('board')->toArray();

    expect($array['editable'])->toBeFalse()
        ->and($array['saveMethod'])->toBeNull()
        ->and($array['lockable'])->toBeFalse()
        ->and($array['lockMethod'])->toBeNull()
        ->and($array['locked'])->toBeFalse()
        ->and($array['lockedSource'])->toBeNull()
        ->and(Layout::kanban('board')->childRenderables())->toBe([]);
});

it('serializes editing, locking and the lock from a flag or a state key', function (): void {
    $array = Layout::kanban('board')
        ->editable('saveBoard')
        ->lockable('lockBoard')
        ->locked()
        ->toArray();

    expect($array['editable'])->toBeTrue()
        ->and($array['saveMethod'])->toBe('saveBoard')
        ->and($array['lockable'])->toBeTrue()
        ->and($array['lockMethod'])->toBe('lockBoard')
        ->and($array['locked'])->toBeTrue()
        ->and($array['lockedSource'])->toBeNull();

    $fromState = Layout::calendar('events')->locked('events_locked')->toArray();
    expect($fromState['locked'])->toBeFalse()
        ->and($fromState['lockedSource'])->toBe('events_locked');
});

it('declares the methods it calls, with their permissions, for the server check', function (): void {
    $layout = Layout::kanban('b')->editable('save', 'board.edit')->lockable('lock', 'board.lock');
    $declared = $layout->childRenderables();

    expect(array_map(static fn ($a) => $a->getAttribute('method'), $declared))->toBe(['save', 'lock'])
        ->and($declared[0]->getPermission())->toBe('board.edit')
        ->and($declared[1]->getPermission())->toBe('board.lock')
        ->and($declared[0]->name())->toBe('kanban-save');
});

it('turns editing and locking off without the permission', function (): void {
    // No user: a permission is never granted.
    $array = Layout::kanban('b')->editable('save', 'board.edit')->lockable('lock', 'board.lock')->toArray();

    expect($array['editable'])->toBeFalse()
        ->and($array['saveMethod'])->toBeNull()
        ->and($array['lockable'])->toBeFalse()
        ->and($array['lockMethod'])->toBeNull();
});

it('serializes the calendar view, date and views', function (): void {
    $array = Layout::calendar('schedule')
        ->initialView('week')
        ->date('2026-11-02')
        ->views(['week', 'month', 'agenda'])
        ->editable('saveSchedule')
        ->toArray();

    expect($array['view'])->toBe('week')
        ->and($array['date'])->toBe('2026-11-02')
        ->and($array['views'])->toBe(['week', 'month', 'agenda'])
        ->and($array['editable'])->toBeTrue();
});

it('rejects an unknown calendar view, a bad date and no views', function (): void {
    expect(fn () => Layout::calendar('c')->initialView('fortnight'))->toThrow(InvalidArgumentException::class, 'Unknown calendar view')
        ->and(fn () => Layout::calendar('c')->views(['week', 'decade']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::calendar('c')->views([]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::calendar('c')->date('2026-13-01'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::calendar('c')->date('02.11.2026'))->toThrow(InvalidArgumentException::class);
});
