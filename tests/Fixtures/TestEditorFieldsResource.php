<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Bpmn;
use Dskripchenko\LaravelAdmin\Field\Calendar;
use Dskripchenko\LaravelAdmin\Field\Dmn;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Field\Kanban;
use Dskripchenko\LaravelAdmin\Field\MarkdownEditor;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Illuminate\Database\Eloquent\Model;

/**
 * A record with every editor field: BPMN, DMN, kanban, calendar, Markdown.
 *
 * @internal
 */
final class TestEditorRecord extends Model
{
    protected $table = 'test_editor_records';

    protected $guarded = [];
}

/**
 * @internal
 */
final class TestEditorFieldsResource extends Resource
{
    public static string $model = TestEditorRecord::class;

    public function fields(): array
    {
        return [
            Input::make('title')->required(),
            Bpmn::make('process'),
            Dmn::make('rules'),
            Kanban::make('board')->maxLength(2000),
            Calendar::make('schedule')->initialView('week'),
            MarkdownEditor::make('notes'),
        ];
    }
}
