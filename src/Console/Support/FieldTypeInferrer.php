<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Console\Support;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Guesses the form field, the list column and the filter for a database
 * column: from its name, its type, nullability, the enum values and — when a
 * model is known — its casts and its $hidden attributes.
 *
 * The resource generator uses it to produce a resource that works as soon as
 * it is written, leaving the host only to tidy it up. Every method it emits
 * exists on the class it is called on; GeneratedResourceContractTest holds
 * the generator to that by loading and serving the generated classes.
 *
 * The column metadata comes from SchemaIntrospector::analyzeTable(); the
 * optional context carries what the model adds:
 *
 *   - hidden:  the model's $hidden — kept out of the form, the list and the search;
 *   - casts:   the model's casts — they refine the database type (a tinyint
 *              cast to bool is a boolean, a text cast to array is JSON, a
 *              column cast to a backed enum is a select of its cases);
 *   - columns: every column name of the table, for the cross-column guesses
 *              (a slug follows `title` only when the table has one).
 *
 * @phpstan-type Column array{name: string, type: string, full_type?: string, nullable?: bool, default?: mixed, comment?: ?string, is_primary?: bool, is_unique?: bool, is_indexed?: bool, enum_values?: ?list<string>}
 * @phpstan-type Relation array{name: string, type: string, related: ?string, foreign_key: ?string, owner_key?: ?string, display?: string}
 * @phpstan-type Context array{hidden?: list<string>, casts?: array<string, string>, columns?: list<string>}
 */
final class FieldTypeInferrer
{
    /** Columns the database or Eloquent fills in by itself. */
    private const SYSTEM_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /** Names that hold a secret whatever the model says. */
    private const SECRET_NAMES = ['password', 'remember_token', 'token', 'secret', 'api_key', 'api_token', 'access_token', 'refresh_token'];

    /**
     * Whether a column holds a secret, or something the model hides: it then
     * never reaches the form, the list, the filters or the search. That is
     * `password`, `remember_token`, any `*_token`, `*_secret`, `*_password`,
     * `two_factor_*`, a column cast to `hashed`, and the model's $hidden.
     *
     * @param  Context  $context
     */
    public function isSecret(string $name, array $context = []): bool
    {
        $lower = strtolower($name);
        if (in_array($name, $context['hidden'] ?? [], true)) {
            return true;
        }
        if (($context['casts'][$name] ?? null) === 'hashed') {
            return true;
        }
        if (in_array($lower, self::SECRET_NAMES, true)) {
            return true;
        }

        return str_ends_with($lower, '_token')
            || str_ends_with($lower, '_secret')
            || str_ends_with($lower, '_password')
            || str_starts_with($lower, 'two_factor_');
    }

    /**
     * The column's kind, one of: boolean, date, datetime, time, integer,
     * decimal, text, json, uuid, enum, string. The model's cast wins over the
     * database type, since SQLite and MySQL store a boolean as an integer.
     *
     * @param  Column  $col
     * @param  Context  $context
     */
    public function kind(array $col, array $context = []): string
    {
        if ($this->enumValues($col, $context) !== []) {
            return 'enum';
        }

        $cast = $context['casts'][$col['name']] ?? null;
        if (is_string($cast)) {
            $byCast = $this->kindFromCast($cast);
            if ($byCast !== null) {
                return $byCast;
            }
        }

        $type = strtolower($col['type']);
        $full = strtolower($col['full_type'] ?? $col['type']);

        return match (true) {
            in_array($type, ['bool', 'boolean'], true), $full === 'tinyint(1)', $type === 'tinyint(1)' => 'boolean',
            $type === 'date' => 'date',
            in_array($type, ['datetime', 'datetimetz', 'timestamp', 'timestamptz'], true),
            str_starts_with($type, 'timestamp') => 'datetime',
            in_array($type, ['time', 'timetz'], true), str_starts_with($type, 'time ') => 'time',
            in_array($type, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int2', 'int4', 'int8', 'serial', 'bigserial', 'year'], true) => 'integer',
            in_array($type, ['decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8', 'money', 'double precision'], true) => 'decimal',
            in_array($type, ['text', 'mediumtext', 'longtext', 'tinytext', 'clob'], true) => 'text',
            in_array($type, ['json', 'jsonb'], true) => 'json',
            $type === 'uuid' => 'uuid',
            default => 'string',
        };
    }

    /**
     * The allowed values of an enum column: from the database (a MySQL ENUM,
     * an SQLite or PostgreSQL CHECK … IN), or from a backed enum the model
     * casts the column to.
     *
     * @param  Column  $col
     * @param  Context  $context
     * @return list<string>
     */
    public function enumValues(array $col, array $context = []): array
    {
        if (! empty($col['enum_values'])) {
            return array_map('strval', $col['enum_values']);
        }

        $enum = $this->enumCast($col['name'], $context);
        if ($enum !== null) {
            return array_map(
                static fn (BackedEnum $case): string => (string) $case->value,
                $enum::cases(),
            );
        }

        return [];
    }

    /**
     * Returns the line of code for one field of `fields()`, or null when the
     * column has no business in the form.
     *
     * @param  Column  $col
     * @param  list<Relation>  $relations
     * @param  Context  $context
     */
    public function inferFieldCode(array $col, array $relations = [], array $context = []): ?string
    {
        $name = $col['name'];

        if (in_array($name, self::SYSTEM_COLUMNS, true) || ($col['is_primary'] ?? false) || $this->isSecret($name, $context)) {
            return null;
        }

        $title = $this->q($this->humanize($name));
        $required = ! ($col['nullable'] ?? true) && ($col['default'] ?? null) === null;

        $relation = $this->belongsTo($name, $relations);
        if ($relation !== null) {
            $code = 'RelationSelect::make('.$this->q($name).')->relation('
                .$this->classRef((string) $relation['related']).', '.$this->q($relation['display'] ?? 'name').')'
                ."->title({$title})";

            return $required ? $code.'->required()' : $code;
        }

        $kind = $this->kind($col, $context);
        $n = $this->q($name);

        if ($kind === 'enum') {
            $enum = $this->enumCast($name, $context);
            $code = $enum !== null
                ? "Select::make({$n})->fromEnum(".$this->classRef($enum).')'
                : "Select::make({$n})->options(".$this->optionsArray($this->enumValues($col, $context)).')';
            $code .= "->title({$title})";

            return $required ? $code.'->required()' : $code;
        }

        $code = $this->fieldByName($name, $kind, $context)
            ?? match ($kind) {
                'boolean' => "Switcher::make({$n})",
                'date' => "DatePicker::make({$n})",
                'datetime' => "DatePicker::make({$n})->withTime()",
                'time' => "TimePicker::make({$n})",
                'integer' => "Number::make({$n})->integer()",
                'decimal' => "Number::make({$n})->step(0.01)",
                'text' => "Textarea::make({$n})->rows(4)",
                'json' => $this->isArrayCast($name, $context)
                    ? "KeyValue::make({$n})"
                    : "Code::make({$n})->language('json')",
                'uuid' => "Input::make({$n})->readonly()",
                default => "Input::make({$n})",
            };

        $code .= "->title({$title})";

        // A boolean always has a value, a switch never needs `required`.
        if ($required && $kind !== 'boolean') {
            $code .= '->required()';
        }

        return $code;
    }

    /**
     * Returns the line of code for one TableColumn of `columns()`, or null
     * when the column does not belong in the list.
     *
     * @param  Column  $col
     * @param  Context  $context
     */
    public function inferColumnCode(array $col, array $context = []): ?string
    {
        $name = $col['name'];

        if (in_array($name, ['updated_at', 'deleted_at'], true) || $this->isSecret($name, $context)) {
            return null;
        }

        $kind = $this->kind($col, $context);
        $lower = strtolower($name);
        $base = 'TableColumn::make('.$this->q($name).')->label('.$this->q($this->humanize($name)).')';

        if ($name === 'id' || ($col['is_primary'] ?? false)) {
            return "{$base}->sort()";
        }

        return match (true) {
            $kind === 'datetime' => "{$base}->asDateTime()->sort()",
            $kind === 'date' => "{$base}->asDate()->sort()",
            $kind === 'time' => "{$base}->sort()",
            $kind === 'boolean' => "{$base}->asBoolean()->align('center')",
            $kind === 'enum' => "{$base}->asBadge()->sort()",
            in_array($lower, ['status', 'state', 'type', 'kind', 'role'], true) => "{$base}->asBadge()->sort()",
            str_ends_with($lower, '_id') => "{$base}->sort()",
            in_array($kind, ['integer', 'decimal'], true) && $this->isMoneyName($lower) => "{$base}->asMoney()->align('right')->sort()",
            in_array($kind, ['integer', 'decimal'], true) => "{$base}->align('right')->sort()",
            in_array($kind, ['text', 'json'], true) => "{$base}->defaultHidden()",
            $lower === 'email' || str_ends_with($lower, '_email') => "{$base}->sort()->copyable()",
            default => "{$base}->sort()",
        };
    }

    /**
     * Returns a filter's code, when the column suits a filter at all.
     *
     * @param  Column  $col
     * @param  list<Relation>  $relations
     * @param  Context  $context
     */
    public function inferFilterCode(array $col, array $relations = [], array $context = []): ?string
    {
        $name = $col['name'];
        if ($this->isSecret($name, $context) || in_array($name, ['updated_at', 'deleted_at'], true)) {
            return null;
        }

        $label = '->label('.$this->q($this->humanize($name)).')';
        $n = $this->q($name);

        $relation = $this->belongsTo($name, $relations);
        if ($relation !== null) {
            return "SelectFromModelFilter::for({$n})->fromModel("
                .$this->classRef((string) $relation['related']).', '.$this->q($relation['display'] ?? 'name').')'.$label;
        }

        return match ($this->kind($col, $context)) {
            'enum' => "OptionsFilter::for({$n})->options(".$this->optionsArray($this->enumValues($col, $context)).')'.$label,
            'boolean' => "SwitcherFilter::for({$n})".$label,
            'date', 'datetime' => "DateRangeFilter::for({$n})".$label,
            default => null,
        };
    }

    /**
     * The columns the `?q=` search runs over: up to four plain string columns,
     * the obvious ones (name, title, email…) first. Secrets never.
     *
     * @param  list<Column>  $columns
     * @param  Context  $context
     * @return list<string>
     */
    public function searchableColumns(array $columns, array $context = []): array
    {
        $preferred = ['name', 'title', 'email', 'slug', 'username', 'code', 'label'];
        $candidates = [];
        foreach ($columns as $col) {
            $name = $col['name'];
            if ($this->isSecret($name, $context) || ($col['is_primary'] ?? false)) {
                continue;
            }
            if ($this->kind($col, $context) !== 'string') {
                continue;
            }
            if (str_ends_with(strtolower($name), '_id') || str_ends_with(strtolower($name), '_type')) {
                continue;
            }
            $rank = array_search($name, $preferred, true);
            $candidates[$name] = $rank === false ? 100 : $rank;
        }
        asort($candidates);

        return array_slice(array_keys($candidates), 0, 4);
    }

    /**
     * The casts for a model generated for a table, so that its fields
     * round-trip: a JSON column as an array, a boolean as a bool, the dates as
     * dates.
     *
     * @param  list<Column>  $columns
     * @return array<string, string>
     */
    public function modelCasts(array $columns): array
    {
        $casts = [];
        foreach ($columns as $col) {
            if (in_array($col['name'], self::SYSTEM_COLUMNS, true)) {
                continue;
            }
            $cast = match ($this->kind($col)) {
                'boolean' => 'boolean',
                'json' => 'array',
                'date' => 'date',
                'datetime' => 'datetime',
                default => null,
            };
            if ($cast !== null) {
                $casts[$col['name']] = $cast;
            }
        }

        return $casts;
    }

    public function humanize(string $name): string
    {
        $words = trim(str_replace(['_', '-'], ' ', $name));
        if (strtolower($words) === 'id') {
            return 'ID';
        }
        if (str_ends_with($words, ' id')) {
            $words = substr($words, 0, -3);
        }

        return Str::ucfirst(strtolower($words));
    }

    /**
     * @param  Context  $context
     */
    private function fieldByName(string $name, string $kind, array $context): ?string
    {
        $lower = strtolower($name);
        $n = $this->q($name);

        if ($kind === 'string') {
            if ($lower === 'email' || str_ends_with($lower, '_email')) {
                return "Input::make({$n})->type('email')";
            }
            if (in_array($lower, ['phone', 'tel', 'mobile'], true) || str_ends_with($lower, '_phone')) {
                return "Input::make({$n})->type('tel')";
            }
            if (in_array($lower, ['url', 'website', 'homepage', 'link'], true) || str_ends_with($lower, '_url')) {
                return "Input::make({$n})->type('url')";
            }
            if ($lower === 'slug') {
                $columns = $context['columns'] ?? [];
                $source = in_array('title', $columns, true) ? 'title' : (in_array('name', $columns, true) ? 'name' : null);

                return $source !== null ? "Slug::make({$n})->from('{$source}')" : "Slug::make({$n})";
            }
            if ($lower === 'color' || $lower === 'colour' || str_ends_with($lower, '_color')) {
                return "ColorPicker::make({$n})";
            }
        }

        if (in_array($kind, ['string', 'text'], true)) {
            if (in_array($lower, ['body', 'content', 'html'], true)) {
                return "Wysiwyg::make({$n})";
            }
            if (in_array($lower, ['description', 'excerpt', 'summary', 'bio', 'about', 'notes', 'note'], true)) {
                return "Textarea::make({$n})->rows(4)";
            }
        }

        // A file column stores the path as a string here, while FileUpload
        // sends {disk, path}: it is offered only for a column the model casts
        // to an array, where that value has somewhere to go.
        $isFileName = in_array($lower, ['avatar', 'image', 'photo', 'cover', 'logo', 'picture', 'file', 'attachment', 'document'], true)
            || str_ends_with($lower, '_image') || str_ends_with($lower, '_file');
        if ($isFileName && $this->isArrayCast($name, $context)) {
            $image = ! in_array($lower, ['file', 'attachment', 'document'], true) && ! str_ends_with($lower, '_file');

            return "FileUpload::make({$n})".($image ? '->image()' : '');
        }

        return null;
    }

    private function kindFromCast(string $cast): ?string
    {
        $cast = strtolower($cast);
        $base = explode(':', $cast, 2)[0];

        return match (true) {
            in_array($base, ['bool', 'boolean'], true) => 'boolean',
            in_array($base, ['date', 'immutable_date'], true) => 'date',
            in_array($base, ['datetime', 'immutable_datetime', 'custom_datetime', 'immutable_custom_datetime', 'timestamp'], true) => 'datetime',
            in_array($base, ['int', 'integer'], true) => 'integer',
            in_array($base, ['float', 'double', 'decimal', 'real'], true) => 'decimal',
            in_array($base, ['array', 'json', 'object', 'collection'], true) => 'json',
            in_array($cast, ['encrypted:array', 'encrypted:collection', 'encrypted:object', 'encrypted:json'], true) => 'json',
            str_contains($cast, 'asarrayobject'), str_contains($cast, 'ascollection'), str_contains($cast, 'asencryptedarrayobject') => 'json',
            default => null,
        };
    }

    /**
     * @param  Context  $context
     */
    private function isArrayCast(string $name, array $context): bool
    {
        $cast = $context['casts'][$name] ?? null;

        return is_string($cast) && $this->kindFromCast($cast) === 'json';
    }

    /**
     * @param  Context  $context
     * @return class-string<BackedEnum>|null
     */
    private function enumCast(string $name, array $context): ?string
    {
        $cast = $context['casts'][$name] ?? null;
        if (! is_string($cast) || ! enum_exists($cast) || ! is_subclass_of($cast, BackedEnum::class)) {
            return null;
        }

        return $cast;
    }

    /**
     * @param  list<Relation>  $relations
     * @return Relation|null
     */
    private function belongsTo(string $column, array $relations): ?array
    {
        foreach ($relations as $rel) {
            if ($rel['type'] === 'BelongsTo' && $rel['foreign_key'] === $column && ! empty($rel['related'])) {
                return $rel;
            }
        }

        return null;
    }

    private function isMoneyName(string $lower): bool
    {
        foreach (['price', 'amount', 'total', 'cost', 'balance', 'sum'] as $needle) {
            if ($lower === $needle || str_starts_with($lower, $needle.'_') || str_ends_with($lower, '_'.$needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $values
     */
    private function optionsArray(array $values): string
    {
        $pairs = array_map(
            fn (string $v): string => $this->q($v).' => '.$this->q($this->humanize($v)),
            $values,
        );

        return '['.implode(', ', $pairs).']';
    }

    private function classRef(string $class): string
    {
        return '\\'.ltrim($class, '\\').'::class';
    }

    /**
     * A single-quoted PHP string literal.
     */
    private function q(string $value): string
    {
        return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $value)."'";
    }
}
