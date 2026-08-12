<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use Illuminate\Support\Str;

/**
 * Guesses a field class and its parameters from a column's metadata: the name,
 * the type, whether it is nullable or indexed, and the enum values.
 *
 * The resource generator uses it to produce a working fields() right away,
 * leaving the host only to tidy it up.
 */
final class FieldTypeInferrer
{
    /**
     * Returns the line of code for one field of the `fields()` array,
     * including the rules and the helpers: placeholder, title, required,
     * options.
     *
     * @param  array{name: string, type: string, nullable: bool, default: mixed, comment: ?string, is_unique: bool, is_indexed: bool, enum_values: ?list<string>}  $col
     * @param  list<array{name: string, type: string, related: ?class-string, foreign_key: ?string, owner_key: ?string}>  $relations
     */
    public function inferFieldCode(array $col, array $relations = []): ?string
    {
        $name = $col['name'];

        // Skip auto/primary/timestamp/soft-delete columns from form
        if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'], true)) {
            return null;
        }

        // A foreign-key column becomes a RelationSelect, when the matching relation exists
        foreach ($relations as $rel) {
            if ($rel['type'] === 'BelongsTo' && $rel['foreign_key'] === $name) {
                return $this->relationSelect($name, $rel);
            }
        }

        $type = strtolower($col['type']);
        $required = ! $col['nullable'] && $col['default'] === null;
        $title = $this->humanize($name);

        // Enum
        if (! empty($col['enum_values'])) {
            return $this->selectFromEnum($name, $title, $col['enum_values'], $required);
        }

        // The shortcuts by name pattern
        $pattern = $this->detectByName($name);

        // Type-mapping
        $code = match (true) {
            $pattern !== null => $pattern,
            in_array($type, ['boolean', 'bool', 'tinyint(1)'], true) => "Switcher::make('{$name}')->title('{$title}')",
            in_array($type, ['date'], true) => "DatePicker::make('{$name}')->title('{$title}')",
            in_array($type, ['datetime', 'timestamp'], true) => "DatePicker::make('{$name}')->withTime()->title('{$title}')",
            in_array($type, ['time'], true) => "TimePicker::make('{$name}')->title('{$title}')",
            in_array($type, ['integer', 'int', 'bigint', 'smallint', 'mediumint'], true) => "Number::make('{$name}')->integer()->title('{$title}')",
            in_array($type, ['decimal', 'float', 'double', 'numeric'], true) => "Number::make('{$name}')->step(0.01)->title('{$title}')",
            in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext'], true) => "Textarea::make('{$name}')->rows(4)->title('{$title}')",
            in_array($type, ['json', 'jsonb'], true) => "KeyValue::make('{$name}')->title('{$title}')",
            in_array($type, ['uuid', 'char'], true) && ($col['name'] === 'uuid' || str_ends_with($name, '_uuid')) => "Hidden::make('{$name}')",
            default => "Input::make('{$name}')->title('{$title}')",
        };

        if ($required && ! str_contains($code, '->required(')) {
            $code = $this->insertModifier($code, '->required()');
        }

        return $code;
    }

    /**
     * Returns the line of code for a TableColumn, for columns().
     *
     * @param  array{name: string, type: string, is_indexed: bool, enum_values: ?list<string>}  $col
     */
    public function inferColumnCode(array $col): ?string
    {
        $name = $col['name'];

        if (in_array($name, ['updated_at', 'remember_token', 'password', 'deleted_at'], true)) {
            return null;
        }

        $title = $this->humanize($name);
        $type = strtolower($col['type']);

        $base = "TableColumn::make('{$name}')";

        if ($name === 'id') {
            return "{$base}->sortable()";
        }
        if (in_array($name, ['created_at'], true) || in_array($type, ['datetime', 'timestamp'], true)) {
            return "{$base}->preset('datetime')->sortable()";
        }
        if ($type === 'date') {
            return "{$base}->preset('date')->sortable()";
        }
        if (in_array($type, ['boolean', 'bool', 'tinyint(1)'], true)) {
            return "{$base}->preset('boolean')->align('center')";
        }
        if (in_array($name, ['status', 'state', 'type', 'kind', 'role'], true) || ! empty($col['enum_values'])) {
            return "{$base}->preset('badge')";
        }
        if (str_contains($name, 'price') || str_contains($name, 'amount') || str_contains($name, 'total')) {
            return "{$base}->preset('money')->align('right')->sortable()";
        }
        if ($col['is_indexed'] || $col['name'] === 'name' || $col['name'] === 'title' || $col['name'] === 'email') {
            return "{$base}->sortable()->searchable()";
        }
        if (in_array($type, ['text', 'mediumtext', 'longtext'], true)) {
            return "{$base}->preset('truncate')";
        }

        return "{$base}->sortable()";
    }

    /**
     * Returns a filter's code, when the column suits a filter at all.
     *
     * @param  array{name: string, type: string, is_indexed: bool, enum_values: ?list<string>}  $col
     */
    public function inferFilterCode(array $col): ?string
    {
        $name = $col['name'];
        $type = strtolower($col['type']);

        if (! empty($col['enum_values'])) {
            $opts = array_map(
                fn (string $v): string => "'{$v}' => '".$this->humanize($v)."'",
                $col['enum_values'],
            );

            return "BaseSelectFromOptionsFilter::make('{$name}')->options(["
                .implode(', ', $opts).'])';
        }

        if (in_array($type, ['boolean', 'bool', 'tinyint(1)'], true)) {
            return "BaseSwitcherFilter::make('{$name}')";
        }

        if (in_array($type, ['date', 'datetime', 'timestamp'], true)) {
            return "BaseDateFilter::make('{$name}')->range()";
        }

        return null;
    }

    private function detectByName(string $name): ?string
    {
        $title = $this->humanize($name);
        $lower = strtolower($name);

        if ($lower === 'email' || str_ends_with($lower, '_email')) {
            return "Input::make('{$name}')->type('email')->title('{$title}')";
        }
        if ($lower === 'password' || str_ends_with($lower, '_password')) {
            return "Input::make('{$name}')->type('password')->title('{$title}')";
        }
        if ($lower === 'phone' || $lower === 'tel' || str_ends_with($lower, '_phone')) {
            return "Input::make('{$name}')->type('tel')->title('{$title}')";
        }
        if (in_array($lower, ['url', 'website', 'homepage', 'link'], true) || str_ends_with($lower, '_url')) {
            return "Input::make('{$name}')->type('url')->title('{$title}')";
        }
        if ($lower === 'slug') {
            return "Slug::make('{$name}')->from('title')->title('{$title}')";
        }
        if ($lower === 'color' || str_ends_with($lower, '_color')) {
            return "Input::make('{$name}')->type('color')->title('{$title}')";
        }
        if (in_array($lower, ['avatar', 'image', 'photo', 'cover', 'logo'], true) || str_ends_with($lower, '_image')) {
            return "FileUpload::make('{$name}')->accept(['image/*'])->title('{$title}')";
        }
        if (in_array($lower, ['file', 'attachment', 'document'], true) || str_ends_with($lower, '_file')) {
            return "FileUpload::make('{$name}')->title('{$title}')";
        }
        if ($lower === 'description' || $lower === 'excerpt' || $lower === 'summary' || $lower === 'bio' || $lower === 'about') {
            return "Textarea::make('{$name}')->rows(4)->title('{$title}')";
        }
        if (in_array($lower, ['body', 'content', 'html'], true)) {
            return "Wysiwyg::make('{$name}')->title('{$title}')";
        }

        return null;
    }

    /**
     * @param  array{name: string, type: string, related: ?class-string, foreign_key: ?string}  $rel
     */
    private function relationSelect(string $columnName, array $rel): string
    {
        $relName = $rel['name'];
        $title = $this->humanize($relName);

        // The display column would better come from the related model,
        // through the introspector, but at inference time we have no access to
        // it: 'name' is the default, and the host corrects it if need be.
        return "RelationSelect::make('{$columnName}')->relation('{$relName}')->display('name')->searchable()->title('{$title}')";
    }

    /**
     * @param  list<string>  $values
     */
    private function selectFromEnum(string $name, string $title, array $values, bool $required): string
    {
        $opts = array_map(
            fn (string $v): string => "'{$v}' => '".$this->humanize($v)."'",
            $values,
        );
        $code = "Select::make('{$name}')->options(["
            .implode(', ', $opts).'])'
            ."->title('{$title}')";
        if ($required) {
            $code .= '->required()';
        }

        return $code;
    }

    private function insertModifier(string $code, string $modifier): string
    {
        // Inserted before the closing quote, at the end of the chain.
        return $code.$modifier;
    }

    public function humanize(string $name): string
    {
        $words = str_replace(['_', '-'], ' ', $name);

        return Str::title($words);
    }
}
