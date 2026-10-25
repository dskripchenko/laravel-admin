<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Flow as FlowField;
use Dskripchenko\LaravelAdmin\Field\Rules\FlowModel;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Infolist\FlowEntry;
use Dskripchenko\LaravelAdmin\Layout\Flow;
use Dskripchenko\LaravelAdmin\Layout\Layout;
use Dskripchenko\LaravelAdmin\Support\FlowGraph;
use Illuminate\Support\Facades\Validator;

function flowOrder(): array
{
    return [
        'version' => 1,
        'meta' => ['title' => 'Order'],
        'nodes' => [
            ['id' => 'start', 'type' => 'start'],
            ['id' => 'new', 'type' => 'state', 'label' => 'New', 'data' => []],
            ['id' => 'pay', 'type' => 'subprocess', 'label' => 'Payment', 'subgraph' => [
                'nodes' => [['id' => 'a'], ['id' => 'b']],
                'edges' => [['id' => 'x', 'from' => 'a', 'to' => 'b']],
            ]],
            ['id' => 'end', 'type' => 'end'],
        ],
        'edges' => [
            ['id' => 'e1', 'from' => 'start', 'to' => 'new'],
            ['id' => 'e2', 'from' => 'new', 'to' => 'pay', 'label' => 'checkout'],
            ['id' => 'e3', 'from' => 'pay', 'to' => 'end'],
        ],
    ];
}

/* ---------------- the model ---------------- */

it('accepts a valid model, as an array or as JSON', function (): void {
    expect(FlowGraph::errors(flowOrder()))->toBe([])
        ->and(FlowGraph::parse(json_encode(flowOrder())))->toBe(flowOrder());
});

it('reports the shape errors with a pointer', function (array $graph, string $pointer): void {
    $errors = FlowGraph::errors($graph);

    expect($errors)->not->toBe([])
        ->and($errors[0])->toStartWith($pointer.': ');
})->with([
    'not an object' => [['a', 'b'], '/'],
    'no nodes' => [['edges' => []], '/nodes'],
    'wrong version' => [['version' => 2, 'nodes' => []], '/version'],
    'empty id' => [['nodes' => [['id' => '']]], '/nodes/0/id'],
    'duplicate id across kinds' => [['nodes' => [['id' => 'a']], 'edges' => [['id' => 'a', 'from' => 'a', 'to' => 'a']]], '/edges/0/id'],
    'edge to a missing node' => [['nodes' => [['id' => 'a']], 'edges' => [['id' => 'e', 'from' => 'a', 'to' => 'zz']]], '/edges/0/to'],
    'bad position' => [['nodes' => [['id' => 'a', 'position' => ['x' => 1]]]], '/nodes/0/position'],
    'missing group' => [['nodes' => [['id' => 'a', 'group' => 'g']]], '/nodes/0/group'],
    'broken inline sub-process' => [['nodes' => [['id' => 'a', 'subgraph' => ['nodes' => [['id' => 'a1']], 'edges' => [['id' => 'e', 'from' => 'a1', 'to' => 'q']]]]]], '/nodes/0/subgraph/edges/0/to'],
    'bad edge kind' => [['nodes' => [['id' => 'a']], 'edges' => [['id' => 'e', 'from' => 'a', 'to' => 'a', 'kind' => 'x']]], '/edges/0/kind'],
]);

it('restricts the node types when asked', function (): void {
    $errors = FlowGraph::errors(flowOrder(), ['start', 'state', 'end']);

    expect($errors)->toHaveCount(1)
        ->and($errors[0])->toStartWith('/nodes/2/type');
});

it('keeps empty data and meta objects for JSON and translates captions', function (): void {
    $out = FlowGraph::forJson(flowOrder(), static fn (string $s): string => strtoupper($s));
    $json = json_encode($out);

    expect($json)->toContain('"data":{}')
        ->and($out['nodes'][1]['label'])->toBe('NEW')
        ->and($out['meta']['title'])->toBe('ORDER')
        ->and($out['nodes'][2]['subgraph']['nodes'][0]['id'])->toBe('a')
        ->and($out['edges'][1]['label'])->toBe('CHECKOUT');
});

/* ---------------- Layout::flow ---------------- */

it('serializes an inline flow with its options', function (): void {
    $array = Layout::flow(flowOrder())
        ->title('Orders')
        ->direction('LR')
        ->edgeStyle('smooth')
        ->height(560)
        ->minimap(false)
        ->details(false)
        ->statusBar(false)
        ->toggleable()
        ->snapshot(['nodes' => ['new' => ['status' => 'active']]])
        ->subgraphs(['payment' => ['nodes' => [['id' => 'p']]]])
        ->instance(7)
        ->toArray();

    expect(Layout::flow('x'))->toBeInstanceOf(Flow::class)
        ->and($array['kind'])->toBe('layout')
        ->and($array['type'])->toBe('flow')
        ->and($array['title'])->toBe('Orders')
        ->and($array['direction'])->toBe('LR')
        ->and($array['edgeStyle'])->toBe('smooth')
        ->and($array['height'])->toBe('560px')
        ->and($array['minimap'])->toBeFalse()
        ->and($array['details'])->toBeFalse()
        ->and($array['statusBar'])->toBeFalse()
        ->and($array['toggleable'])->toBeTrue()
        ->and($array['graph']['nodes'][1]['id'])->toBe('new')
        ->and($array['source'])->toBeNull()
        ->and($array['snapshot'])->toBe(['nodes' => ['new' => ['status' => 'active']]])
        ->and($array['poll'])->toBeNull()
        ->and($array['toggle'])->toBeNull()
        ->and($array['subgraphs']['method'])->toBeNull()
        ->and((array) $array['subgraphs']['graphs'])->toHaveKey('payment')
        ->and($array['instance'])->toBe(7)
        ->and($array['actions'])->toBe([])
        // The model travels once, at the top level.
        ->and($array['props'])->not->toHaveKey('graph');
});

it('reads the model, the snapshot and the instance from state keys', function (): void {
    $array = Layout::flow('process')
        ->snapshot('live')
        ->instanceFrom('order_id')
        ->poll('state', 2.5)
        ->subgraphs('loadSub')
        ->onToggle('toggle')
        ->toArray();

    expect($array['source'])->toBe('process')
        ->and($array['graph'])->toBeNull()
        ->and($array['snapshotSource'])->toBe('live')
        ->and($array['instanceSource'])->toBe('order_id')
        ->and($array['poll'])->toBe(['method' => 'state', 'interval' => 2500])
        ->and($array['subgraphs']['method'])->toBe('loadSub')
        ->and($array['toggle'])->toBe(['method' => 'toggle'])
        ->and($array['toggleable'])->toBeTrue();
});

it('declares the methods it calls, for the server check', function (): void {
    $layout = Layout::flow('p')->poll('state')->onToggle('toggle', 'flow.manage')->subgraphs('sub');
    $methods = array_map(static fn ($a) => $a->getAttribute('method'), $layout->childRenderables());

    expect($methods)->toBe(['state', 'sub', 'toggle'])
        ->and($layout->childRenderables()[2]->getPermission())->toBe('flow.manage');
});

it('rejects an invalid inline model and unknown options', function (): void {
    expect(fn () => Layout::flow(['nodes' => [['id' => 'a'], ['id' => 'a']]]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::flow('{"nodes": 1}'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::flow(''))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::flow('p')->direction('RL'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Layout::flow('p')->edgeStyle('curvy'))->toThrow(InvalidArgumentException::class);
});

it('takes a JSON model inline', function (): void {
    expect(Layout::flow(json_encode(flowOrder()))->graph())->toBe(flowOrder());
});

/* ---------------- Field\Flow ---------------- */

it('serializes the flow field', function (): void {
    $array = FlowField::make('process')
        ->title('Process')
        ->palette(['start', 'state', 'end'])
        ->locked(['start'])
        ->height(600)
        ->direction('TB')
        ->edgeStyle('smooth')
        ->minimap()
        ->snapGrid(10)
        ->mermaidImport(false)
        ->exportName('order.json')
        ->readonly()
        ->toArray();

    expect($array['type'])->toBe('flow')
        ->and($array['label'])->toBe('Process')
        ->and($array['attributes'])->toMatchArray([
            'palette' => ['start', 'state', 'end'],
            'locked' => ['start'],
            'height' => '600px',
            'direction' => 'TB',
            'edgeStyle' => 'smooth',
            'minimap' => true,
            'snapGrid' => 10,
            'mermaid' => false,
            'exportName' => 'order.json',
            'readonly' => true,
        ]);
});

it('exports the model rule with the field', function (): void {
    $rules = ValidationRulesExporter::export([
        FlowField::make('process')->required()->palette(['state', 'end'])->locked(['start']),
    ]);

    expect($rules['process'])->toContain('required', 'array')
        ->and(collect($rules['process'])->first(fn ($r) => $r instanceof FlowModel))->not->toBeNull();

    $ok = Validator::make(['process' => ['nodes' => [['id' => 's', 'type' => 'start'], ['id' => 'a', 'type' => 'state']]]], $rules);
    expect($ok->passes())->toBeTrue();

    $bad = Validator::make(['process' => flowOrder()], $rules);
    expect($bad->fails())->toBeTrue()
        ->and($bad->errors()->first('process'))->toContain('/nodes/2/type');
});

it('validates a model with the stand-alone rule', function (): void {
    $passes = static fn (mixed $value): bool => Validator::make(['p' => $value], ['p' => [FlowField::rule()]])->passes();

    expect($passes(flowOrder()))->toBeTrue()
        ->and($passes(json_encode(flowOrder())))->toBeTrue()
        ->and($passes(null))->toBeTrue()
        ->and($passes('{broken'))->toBeFalse()
        ->and($passes(['nodes' => [['id' => 'a']], 'edges' => [['id' => 'e', 'from' => 'a', 'to' => 'b']]]))->toBeFalse();
});

/* ---------------- the infolist entry ---------------- */

it('serializes the flow entry', function (): void {
    $array = FlowEntry::make('process')->height(420)->direction('LR')->edgeStyle('smooth')->minimap()->toArray();

    expect($array['type'])->toBe('flow')
        ->and($array['attributes'])->toMatchArray([
            'height' => '420px', 'direction' => 'LR', 'edgeStyle' => 'smooth', 'minimap' => true,
        ]);
});
