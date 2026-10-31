<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Bpmn;
use Dskripchenko\LaravelAdmin\Field\Calendar;
use Dskripchenko\LaravelAdmin\Field\Dmn;
use Dskripchenko\LaravelAdmin\Field\Kanban;
use Dskripchenko\LaravelAdmin\Field\MarkdownEditor;
use Dskripchenko\LaravelAdmin\Field\Rules\BpmnModel;
use Dskripchenko\LaravelAdmin\Field\Rules\DmnModel;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Infolist\BpmnEntry;
use Dskripchenko\LaravelAdmin\Infolist\CalendarEntry;
use Dskripchenko\LaravelAdmin\Infolist\DmnEntry;
use Dskripchenko\LaravelAdmin\Infolist\FieldEntry;
use Dskripchenko\LaravelAdmin\Infolist\KanbanEntry;
use Dskripchenko\LaravelAdmin\Infolist\MarkdownEntry;
use Illuminate\Support\Facades\Validator;

function editorModel(string $file): string
{
    return (string) file_get_contents(__DIR__.'/../Fixtures/models/'.$file);
}

/** The first validation message for $value under $rule, or null when it passes. */
function editorRuleError(mixed $value, object $rule): ?string
{
    $validator = Validator::make(['v' => $value], ['v' => [$rule]]);

    return $validator->fails() ? $validator->errors()->first('v') : null;
}

/* ---------------- serialization ---------------- */

it('serializes the BPMN field with its options', function (): void {
    $array = Bpmn::make('process')
        ->title('Process')
        ->height(640)
        ->properties()
        ->diagram('Collaboration_1')
        ->maxSize(1024)
        ->readonly()
        ->toArray();

    expect($array['type'])->toBe('bpmn')
        ->and($array['label'])->toBe('Process')
        ->and($array['attributes'])->toMatchArray([
            'height' => '640px',
            'properties' => true,
            'diagram' => 'Collaboration_1',
            'maxSize' => 1024,
            'readonly' => true,
        ]);
});

it('serializes the DMN field with its options', function (): void {
    $array = Dmn::make('rules')->height('70vh')->decision('Decision_Discount')->toArray();

    expect($array['type'])->toBe('dmn')
        ->and($array['attributes'])->toMatchArray(['height' => '70vh', 'decision' => 'Decision_Discount']);
});

it('serializes the kanban and calendar fields with their options', function (): void {
    $kanban = Kanban::make('board')->height(480)->maxLength(5000)->disabled()->toArray();
    $calendar = Calendar::make('schedule')
        ->initialView('week')
        ->date('2026-11-02')
        ->views(['week', 'month', 'agenda'])
        ->toArray();

    expect($kanban['type'])->toBe('kanban')
        ->and($kanban['attributes'])->toMatchArray(['height' => '480px', 'maxLength' => 5000, 'disabled' => true])
        ->and($calendar['type'])->toBe('calendar')
        ->and($calendar['attributes'])->toMatchArray([
            'view' => 'week',
            'date' => '2026-11-02',
            'views' => ['week', 'month', 'agenda'],
        ]);
});

it('checks the calendar options as the calendar layout does', function (): void {
    expect(fn () => Calendar::make('c')->initialView('fortnight'))->toThrow(InvalidArgumentException::class, 'Unknown calendar view')
        ->and(fn () => Calendar::make('c')->views(['week', 'decade']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Calendar::make('c')->views([]))->toThrow(InvalidArgumentException::class, 'Field\Calendar->views()')
        ->and(fn () => Calendar::make('c')->date('2026-13-01'))->toThrow(InvalidArgumentException::class, 'Field\Calendar->date()')
        ->and(fn () => Calendar::make('c')->date('02.11.2026'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Kanban::make('b')->maxLength(0))->toThrow(InvalidArgumentException::class)
        ->and(fn () => Bpmn::make('p')->maxSize(-1))->toThrow(InvalidArgumentException::class);
});

it('serializes the Markdown editor with its options', function (): void {
    $array = MarkdownEditor::make('body')
        ->modes(['visual', 'source'])
        ->placeholder('Write…')
        ->minHeight(320)
        ->maxHeight('60vh')
        ->toArray();

    expect($array['type'])->toBe('markdown_editor')
        ->and($array['attributes'])->toMatchArray([
            'modes' => ['visual', 'source'],
            'placeholder' => 'Write…',
            'minHeight' => '320px',
            'maxHeight' => '60vh',
        ])
        ->and(fn () => MarkdownEditor::make('b')->modes(['wysiwyg']))->toThrow(InvalidArgumentException::class)
        ->and(fn () => MarkdownEditor::make('b')->modes([]))->toThrow(InvalidArgumentException::class);
});

/* ---------------- the rules exporter ---------------- */

it('exports the model rules of the BPMN and DMN fields', function (): void {
    $rules = ValidationRulesExporter::export([
        Bpmn::make('process')->required()->maxSize(4096),
        Dmn::make('rules'),
    ]);

    $bpmnRule = collect($rules['process'])->first(fn ($r) => $r instanceof BpmnModel);
    expect($rules['process'])->toContain('required', 'string')
        ->and($bpmnRule)->toBeInstanceOf(BpmnModel::class)
        ->and($rules['rules'])->toContain('nullable', 'string')
        ->and(collect($rules['rules'])->first(fn ($r) => $r instanceof DmnModel))->toBeInstanceOf(DmnModel::class);

    // The field's cap reaches the rule.
    expect(editorRuleError(editorModel('order.bpmn'), $bpmnRule))->toContain('4 KB');
});

it('exports a string with a length cap for the kanban and calendar, a string for the Markdown editor', function (): void {
    $rules = ValidationRulesExporter::export([
        Kanban::make('board')->maxLength(100),
        Calendar::make('schedule'),
        MarkdownEditor::make('notes'),
    ]);

    expect($rules['board'])->toBe(['nullable', 'string', 'max:100'])
        ->and($rules['schedule'])->toBe(['nullable', 'string', 'max:65535'])
        ->and($rules['notes'])->toBe(['nullable', 'string']);

    expect(Validator::make(['board' => str_repeat('x', 101)], $rules)->fails())->toBeTrue()
        ->and(Validator::make(['board' => "## To do\n- Card", 'schedule' => '2026-12-01 10:00 Review', 'notes' => '# Hi'], $rules)->passes())->toBeTrue()
        ->and(Validator::make(['notes' => ['x']], $rules)->fails())->toBeTrue();
});

/* ---------------- the BPMN and DMN rules ---------------- */

it('accepts valid BPMN and DMN files and an empty value', function (): void {
    expect(editorRuleError(editorModel('order.bpmn'), new BpmnModel))->toBeNull()
        ->and(editorRuleError(editorModel('loan.dmn'), new DmnModel))->toBeNull()
        ->and(editorRuleError(editorModel('discount.dmn'), new DmnModel))->toBeNull()
        // DMN 1.1, the namespace ending in dmn.xsd.
        ->and(editorRuleError(editorModel('legacy-11.dmn'), new DmnModel))->toBeNull()
        ->and(editorRuleError(null, new BpmnModel))->toBeNull()
        ->and(editorRuleError('', new DmnModel))->toBeNull();
});

it('rejects what is not a BPMN or DMN file', function (mixed $value, object $rule, string $message): void {
    expect(editorRuleError($value, $rule))->toContain($message);
})->with([
    'not a string' => [['xml'], new BpmnModel, 'XML string'],
    'not XML' => ['just some text', new BpmnModel, 'not readable XML'],
    'broken XML' => ['<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"><process>', new BpmnModel, 'not readable XML'],
    'wrong root' => ['<process xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL"/>', new BpmnModel, 'definitions, not process'],
    'wrong BPMN namespace' => ['<definitions xmlns="http://example.com/bpmn"/>', new BpmnModel, 'http://example.com/bpmn'],
    'no namespace' => ['<definitions/>', new DmnModel, 'namespace'],
    'DMN as BPMN' => [editorModel('loan.dmn'), new BpmnModel, 'namespace'],
    'BPMN as DMN' => [editorModel('order.bpmn'), new DmnModel, 'namespace'],
    'DMN DI namespace' => ['<definitions xmlns="https://www.omg.org/spec/DMN/20191111/DMNDI/"/>', new DmnModel, 'namespace'],
]);

it('rejects a DOCTYPE, an external entity included, without reading it', function (): void {
    $secret = tempnam(sys_get_temp_dir(), 'xxe');
    file_put_contents($secret, 'TOP-SECRET');
    $xxe = '<?xml version="1.0"?>'."\n"
        .'<!DOCTYPE definitions [<!ENTITY xxe SYSTEM "file://'.$secret.'">]>'."\n"
        .'<definitions xmlns="http://www.omg.org/spec/BPMN/20100524/MODEL" id="&xxe;"/>';
    $bomb = '<!doctype definitions [<!ENTITY a "aaaa"><!ENTITY b "&a;&a;&a;&a;">]>'
        .'<definitions xmlns="https://www.omg.org/spec/DMN/20191111/MODEL/">&b;</definitions>';

    try {
        expect(editorRuleError($xxe, new BpmnModel))->toContain('DOCTYPE')
            ->and(editorRuleError($xxe, new BpmnModel))->not->toContain('TOP-SECRET')
            ->and(editorRuleError($bomb, new DmnModel))->toContain('DOCTYPE');
    } finally {
        unlink($secret);
    }
});

it('rejects a file over the size cap before parsing it', function (): void {
    $xml = editorModel('order.bpmn');

    expect(editorRuleError($xml, new BpmnModel(1024)))->toContain('too large')
        ->and(editorRuleError($xml, new BpmnModel(strlen($xml))))->toBeNull()
        ->and(editorRuleError($xml, Bpmn::rule(512)))->toContain('too large')
        ->and(editorRuleError(str_repeat('x', BpmnModel::DEFAULT_MAX_BYTES + 1), new DmnModel))->toContain('2048 KB')
        ->and(editorRuleError(editorModel('loan.dmn'), Dmn::rule()))->toBeNull();
});

/* ---------------- the view page and the infolist entries ---------------- */

it('shows the editor fields on the view page through their own views', function (): void {
    $resource = new TestEditorFieldsResource;
    $entries = collect($resource->infolist())->keyBy(fn ($e) => $e->name());

    foreach (['process' => 'bpmn', 'rules' => 'dmn', 'board' => 'kanban', 'schedule' => 'calendar', 'notes' => 'markdown_editor'] as $name => $type) {
        expect($entries[$name])->toBeInstanceOf(FieldEntry::class)
            ->and($entries[$name]->toArray()['attributes']['field']['type'])->toBe($type);
    }
});

it('serializes the document entries', function (): void {
    expect(BpmnEntry::make('process')->height(420)->highlight(['Task_1'])->diagram(1)->toArray())
        ->toMatchArray(['type' => 'bpmn'])
        ->and(BpmnEntry::make('process')->height(420)->highlight(['Task_1'])->diagram(1)->toArray()['attributes'])
        ->toMatchArray(['height' => '420px', 'highlight' => ['Task_1'], 'diagram' => 1])
        ->and(DmnEntry::make('rules')->decision('D1')->toArray()['attributes'])->toMatchArray(['decision' => 'D1'])
        ->and(DmnEntry::make('rules')->toArray()['type'])->toBe('dmn')
        ->and(KanbanEntry::make('board')->height('50vh')->toArray())->toMatchArray(['type' => 'kanban'])
        ->and(CalendarEntry::make('schedule')->initialView('agenda')->date('2026-11-02')->views(['agenda', 'month'])->toArray()['attributes'])
        ->toMatchArray(['view' => 'agenda', 'date' => '2026-11-02', 'views' => ['agenda', 'month']])
        ->and(MarkdownEntry::make('body')->toArray()['type'])->toBe('markdown')
        ->and(fn () => CalendarEntry::make('s')->initialView('decade'))->toThrow(InvalidArgumentException::class);
});
