<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Http\OpenApi;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;
use Dskripchenko\LaravelAdmin\I18n\Localize;
use Illuminate\Support\Str;

/**
 * Turns Laravel validation rules, plus the Field objects they came from, into
 * an OpenAPI object schema.
 *
 * The rules are the source of truth — they are what `validate()` accepts —
 * and the fields fill in what rules do not say: a label, a default, the
 * options of a select, the shape of a translatable value, the type of a field
 * whose rules are only `nullable`.
 *
 * Only what maps cleanly is mapped: type, format, `required`, `nullable`,
 * `in:` / Rule::in / Rule::enum → enum, min/max/between/size → the bound that
 * fits the type, a flagless `regex:` → pattern, `confirmed` → the
 * `*_confirmation` sibling. Rules with no JSON Schema counterpart (`unique`,
 * `exists`, `required_with`, ...) are left to the validator.
 */
final class RulesSchema
{
    /** More options than this are data, not a vocabulary: no enum then. */
    private const MAX_ENUM = 100;

    /** Rules that make a field required at its own level. */
    private const REQUIRED = ['required', 'present', 'accepted'];

    /**
     * @param  array<string, mixed>  $rules  Validation rules keyed by (dotted) field path.
     * @param  list<Field>  $fields  The fields the rules were exported from.
     * @return array<string, mixed>
     */
    public static function object(array $rules, array $fields = []): array
    {
        /** @var array<string, Field> $byName */
        $byName = [];
        foreach ($fields as $field) {
            $byName[$field->name()] = $field;
        }

        $root = self::node();
        foreach ($rules as $path => $list) {
            $segments = explode('.', (string) $path);
            self::place($root, $segments, self::normalize($list), $byName[$segments[0]] ?? null);
        }

        $schema = self::finish($root);
        $schema['type'] = 'object';
        $schema['properties'] ??= [];

        return $schema;
    }

    /**
     * @return array{rules: list<mixed>, field: ?Field, children: array<string, mixed>, items: ?array<string, mixed>, required: list<string>}
     */
    private static function node(): array
    {
        return ['rules' => [], 'field' => null, 'children' => [], 'items' => null, 'required' => []];
    }

    /**
     * @param  array<string, mixed>  $node
     * @param  list<string>  $segments
     * @param  list<mixed>  $rules
     */
    private static function place(array &$node, array $segments, array $rules, ?Field $field): void
    {
        $name = array_shift($segments);
        $last = $segments === [];

        if ($name === '*') {
            $node['items'] ??= self::node();
            $target = &$node['items'];
        } else {
            $node['children'][$name] ??= self::node();
            $target = &$node['children'][$name];

            if ($last && self::hasAny($rules, self::REQUIRED)) {
                $node['required'][] = $name;
            }

            // `confirmed` expects a twin field the validator compares with.
            if ($last && in_array('confirmed', $rules, true)) {
                $twin = $name.'_confirmation';
                $node['children'][$twin] ??= self::node();
                $node['children'][$twin]['rules'] = array_values(array_filter(
                    $rules,
                    static fn ($rule): bool => $rule !== 'confirmed' && ! (is_string($rule) && str_starts_with($rule, 'unique')),
                ));
                if (self::hasAny($rules, self::REQUIRED)) {
                    $node['required'][] = $twin;
                }
            }
        }

        if ($last) {
            $target['rules'] = array_merge($target['rules'], $rules);
            $target['field'] ??= $field;

            return;
        }

        // A field's own metadata applies to its root only.
        self::place($target, $segments, $rules, null);
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private static function finish(array $node): array
    {
        /** @var list<mixed> $rules */
        $rules = $node['rules'];
        /** @var Field|null $field */
        $field = $node['field'];

        $schema = self::forRules($rules, $field);

        /** @var array<string, array<string, mixed>> $children */
        $children = $node['children'];
        if ($children !== []) {
            // `array` in Laravel is also an associative array — an object.
            $schema['type'] = 'object';
            unset($schema['minItems'], $schema['maxItems'], $schema['items']);
            $properties = $schema['properties'] ?? [];
            foreach ($children as $name => $child) {
                $properties[$name] = self::finish($child);
            }
            $schema['properties'] = $properties;
            /** @var list<string> $required */
            $required = $node['required'];
            if ($required !== []) {
                $schema['required'] = array_values(array_unique(array_merge($schema['required'] ?? [], $required)));
            }
        }

        if (is_array($node['items'])) {
            $schema['type'] = 'array';
            $schema['items'] = self::finish($node['items']);
        }

        return $schema;
    }

    /**
     * @param  list<mixed>  $rules
     * @return array<string, mixed>
     */
    private static function forRules(array $rules, ?Field $field): array
    {
        $strings = array_values(array_filter($rules, 'is_string'));
        $fieldType = $field?->fieldType();
        $schema = [];

        $type = self::typeFromRules($strings) ?? self::typeFromField($field);
        if ($type !== null) {
            $schema['type'] = $type;
        }

        $format = self::formatOf($strings, $field);
        if ($format !== null) {
            $schema['format'] = $format;
            $schema['type'] ??= 'string';
        }

        $enum = self::enumFromRules($rules) ?? self::enumFromOptions($field);
        if ($enum !== null) {
            $enumType = self::scalarTypeOf($enum);
            if (($schema['type'] ?? null) === 'array') {
                $schema['items'] = array_filter(['type' => $enumType, 'enum' => $enum]);
            } else {
                $schema['enum'] = $enum;
                $schema['type'] ??= $enumType;
            }
        }

        if ($fieldType === 'translatable' && $field instanceof TranslatableInput) {
            $schema = array_merge($schema, self::translatable($field));
        }

        if ($field !== null && $field->fieldType() === 'repeater') {
            $schema['type'] = 'array';
            $schema['items'] = self::repeaterItem($field);
        }

        if ($fieldType === 'tags') {
            $schema['items'] ??= ['type' => 'string'];
        }

        if ($fieldType === 'date_range') {
            $schema['items'] ??= ['type' => 'string'];
        }

        if ($fieldType === 'key_value') {
            $schema['additionalProperties'] ??= ['type' => 'string'];
        }

        if (($schema['type'] ?? null) === 'array') {
            $schema['items'] ??= (object) [];
        }

        $schema = array_merge($schema, self::bounds($strings, $schema['type'] ?? null, $fieldType));

        $pattern = self::patternOf($strings);
        if ($pattern !== null && ($schema['type'] ?? 'string') === 'string') {
            $schema['pattern'] = $pattern;
        }

        if (in_array('nullable', $strings, true)) {
            $schema['nullable'] = true;
        }

        $description = self::describe($strings, $field);
        if ($description !== '') {
            $schema['description'] = $description;
        }

        $default = $field?->getDefaultValue();
        if ($default !== null && (is_scalar($default) || is_array($default))) {
            $schema['default'] = $default;
        }

        return $schema;
    }

    /**
     * @param  list<string>  $rules
     */
    private static function typeFromRules(array $rules): ?string
    {
        foreach ([
            'integer' => 'integer',
            'numeric' => 'number',
            'decimal' => 'number',
            'boolean' => 'boolean',
            'array' => 'array',
            'list' => 'array',
            'string' => 'string',
        ] as $rule => $type) {
            foreach ($rules as $candidate) {
                if ($candidate === $rule || str_starts_with($candidate, $rule.':')) {
                    return $type;
                }
            }
        }

        foreach ($rules as $candidate) {
            if (in_array(self::ruleName($candidate), ['email', 'url', 'uuid', 'ulid', 'date', 'date_format', 'ip', 'ipv4', 'ipv6', 'json', 'timezone', 'alpha', 'alpha_num', 'alpha_dash', 'regex'], true)) {
                return 'string';
            }
        }

        return null;
    }

    private static function typeFromField(?Field $field): ?string
    {
        if ($field === null) {
            return null;
        }

        $multiple = ($field->getAttribute('multiple') ?? false) === true;

        return match ($field->fieldType()) {
            'number', 'slider' => ($field->getAttribute('integer') ?? false) === true ? 'integer' : 'number',
            'rating' => 'integer',
            'switch', 'switcher', 'boolean' => 'boolean',
            'checkbox' => $multiple ? 'array' : (self::options($field) === [] ? 'boolean' : null),
            'select', 'radio', 'combobox', 'relation_select', 'tree_select', 'cascader' => $multiple ? 'array' : null,
            'tags', 'date_range', 'repeater', 'builder', 'relation_table' => 'array',
            'key_value', 'translatable', 'group', 'image_cropper', 'morph_switcher' => 'object',
            'file' => $multiple ? 'array' : 'object',
            'input', 'textarea', 'wysiwyg', 'markdown', 'code', 'password', 'slug', 'color', 'date', 'time', 'generated-field' => 'string',
            default => null,
        };
    }

    /**
     * @param  list<string>  $rules
     */
    private static function formatOf(array $rules, ?Field $field): ?string
    {
        $names = array_map(self::ruleName(...), $rules);
        foreach (['email' => 'email', 'url' => 'uri', 'uuid' => 'uuid', 'ipv4' => 'ipv4', 'ipv6' => 'ipv6'] as $rule => $format) {
            if (in_array($rule, $names, true)) {
                return $format;
            }
        }

        if ($field?->fieldType() === 'date') {
            return ($field->getAttribute('withTime') ?? false) === true ? 'date-time' : 'date';
        }
        if ($field?->fieldType() === 'password') {
            return 'password';
        }
        if ($field?->fieldType() === 'input') {
            $html = $field->getAttribute('type');

            return match ($html) {
                'email' => 'email',
                'url' => 'uri',
                'password' => 'password',
                default => null,
            };
        }

        return null;
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<scalar>|null
     */
    private static function enumFromRules(array $rules): ?array
    {
        foreach ($rules as $rule) {
            if ($rule instanceof \Illuminate\Validation\Rules\Enum) {
                $values = self::enumRuleValues($rule);
                if ($values !== null) {
                    return $values;
                }

                continue;
            }

            if ($rule instanceof \Illuminate\Validation\Rules\In) {
                $rule = (string) $rule;
            }

            if (is_string($rule) && str_starts_with($rule, 'in:')) {
                $values = str_getcsv(substr($rule, 3), ',', '"', '');

                return array_map(static fn ($v): string => (string) $v, $values);
            }
        }

        return null;
    }

    /**
     * @return list<scalar>|null
     */
    private static function enumRuleValues(\Illuminate\Validation\Rules\Enum $rule): ?array
    {
        try {
            $property = new \ReflectionProperty($rule, 'type');
            $class = $property->getValue($rule);
        } catch (\ReflectionException) {
            return null;
        }
        if (! is_string($class) || ! enum_exists($class)) {
            return null;
        }

        $values = [];
        /** @var list<\UnitEnum> $cases */
        $cases = $class::cases();
        foreach ($cases as $case) {
            $values[] = $case instanceof \BackedEnum ? $case->value : $case->name;
        }

        return $values;
    }

    /**
     * @return list<scalar>|null
     */
    private static function enumFromOptions(?Field $field): ?array
    {
        if ($field === null) {
            return null;
        }

        $values = [];
        foreach (self::options($field) as $option) {
            $value = is_array($option) ? ($option['value'] ?? null) : null;
            if (! is_scalar($value)) {
                return null;
            }
            $values[] = $value;
        }

        if ($values === [] || count($values) > self::MAX_ENUM) {
            return null;
        }

        return array_values(array_unique($values, SORT_REGULAR));
    }

    /**
     * @return list<mixed>
     */
    private static function options(Field $field): array
    {
        $options = $field->getAttribute('options');

        return is_array($options) ? array_values($options) : [];
    }

    /**
     * @param  list<scalar>  $values
     */
    private static function scalarTypeOf(array $values): ?string
    {
        $types = array_unique(array_map(static fn ($v): string => match (true) {
            is_int($v) => 'integer',
            is_float($v) => 'number',
            is_bool($v) => 'boolean',
            default => 'string',
        }, $values));

        return count($types) === 1 ? $types[0] : null;
    }

    /**
     * @param  list<string>  $rules
     * @return array<string, int|float>
     */
    private static function bounds(array $rules, ?string $type, ?string $fieldType): array
    {
        // A file's min/max are kilobytes, checked at upload time.
        if ($fieldType === 'file' || $fieldType === 'image_cropper' || $type === 'object') {
            return [];
        }

        [$low, $high] = match ($type) {
            'integer', 'number' => ['minimum', 'maximum'],
            'array' => ['minItems', 'maxItems'],
            'string', null => ['minLength', 'maxLength'],
            default => [null, null],
        };
        if ($low === null || $high === null) {
            return [];
        }

        $bounds = [];
        foreach ($rules as $rule) {
            $name = self::ruleName($rule);
            $args = self::ruleArguments($rule);
            $numeric = array_values(array_filter($args, 'is_numeric'));
            if ($numeric === [] || count($numeric) !== count($args)) {
                continue;
            }

            $numbers = array_map(
                static fn (string $v): int|float => in_array($type, ['integer', 'number'], true) ? $v + 0 : (int) $v,
                $numeric,
            );

            if ($name === 'min') {
                $bounds[$low] = $numbers[0];
            } elseif ($name === 'max') {
                $bounds[$high] = $numbers[0];
            } elseif ($name === 'size') {
                $bounds[$low] = $numbers[0];
                $bounds[$high] = $numbers[0];
            } elseif ($name === 'between' && count($numbers) === 2) {
                $bounds[$low] = $numbers[0];
                $bounds[$high] = $numbers[1];
            }
        }

        return $bounds;
    }

    /**
     * A `regex:` rule as an ECMA pattern — only the flagless `/.../` form,
     * since the flags have nowhere to go in JSON Schema.
     *
     * @param  list<string>  $rules
     */
    private static function patternOf(array $rules): ?string
    {
        foreach ($rules as $rule) {
            if (! str_starts_with($rule, 'regex:')) {
                continue;
            }
            $regex = substr($rule, strlen('regex:'));
            if (preg_match('#^/(.*)/$#s', $regex, $m) === 1) {
                return $m[1];
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $rules
     */
    private static function describe(array $rules, ?Field $field): string
    {
        $parts = [];

        if ($field !== null) {
            $title = $field->getAttribute('title');
            $label = is_string($title) ? $title : Str::headline(str_replace('.', ' ', $field->name()));
            $parts[] = (string) Localize::string($label);

            $help = $field->getAttribute('help');
            if (is_string($help) && $help !== '') {
                $parts[] = (string) Localize::string($help);
            }
        }

        foreach ($rules as $rule) {
            if (str_starts_with($rule, 'date_format:')) {
                $parts[] = 'Format: '.substr($rule, strlen('date_format:'));
            }
        }

        return trim(implode('. ', array_filter($parts, static fn (string $p): bool => trim($p) !== '')));
    }

    /**
     * @return array<string, mixed>
     */
    private static function translatable(TranslatableInput $field): array
    {
        $locales = $field->getLocales();
        $properties = [];
        foreach ($locales as $locale) {
            $properties[$locale] = ['type' => 'string'];
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'additionalProperties' => ['type' => 'string'],
        ];
        if (($field->getAttribute('requireAllLocales') ?? false) === true && $locales !== []) {
            $schema['required'] = $locales;
        }

        return $schema;
    }

    /**
     * A repeater's row, from the serialised sub-fields. The rows are not
     * validated field by field on the server, so the item describes their
     * shape, not constraints.
     *
     * @return array<string, mixed>
     */
    private static function repeaterItem(Field $field): array
    {
        $fields = $field->getAttribute('fields');
        $properties = [];
        foreach (is_array($fields) ? $fields : [] as $sub) {
            if (! is_array($sub) || ! is_string($sub['name'] ?? null) || $sub['name'] === '') {
                continue;
            }
            $type = self::typeForSerialisedField($sub);
            $property = $type !== null ? ['type' => $type] : [];
            if (is_string($sub['label'] ?? null) && $sub['label'] !== '') {
                $property['description'] = $sub['label'];
            }
            if ($type === 'array') {
                $property['items'] = (object) [];
            }
            $properties[$sub['name']] = $property === [] ? (object) [] : $property;
        }

        return $properties === []
            ? ['type' => 'object']
            : ['type' => 'object', 'properties' => $properties];
    }

    /**
     * @param  array<array-key, mixed>  $sub
     */
    private static function typeForSerialisedField(array $sub): ?string
    {
        $attributes = is_array($sub['attributes'] ?? null) ? $sub['attributes'] : [];
        $multiple = ($attributes['multiple'] ?? false) === true;

        return match ($sub['type'] ?? null) {
            'number', 'slider' => ($attributes['integer'] ?? false) === true ? 'integer' : 'number',
            'rating' => 'integer',
            'switch' => 'boolean',
            'select', 'radio', 'combobox', 'relation_select', 'checkbox' => $multiple ? 'array' : null,
            'tags', 'date_range', 'repeater' => 'array',
            'key_value', 'translatable', 'file' => 'object',
            'input', 'textarea', 'wysiwyg', 'markdown', 'code', 'password', 'slug', 'color', 'date', 'time' => 'string',
            default => null,
        };
    }

    /**
     * @param  list<mixed>  $rules
     * @param  list<string>  $names
     */
    private static function hasAny(array $rules, array $names): bool
    {
        foreach ($rules as $rule) {
            if (is_string($rule) && in_array($rule, $names, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<mixed>
     */
    private static function normalize(mixed $rules): array
    {
        if (is_string($rules)) {
            return explode('|', $rules);
        }

        return is_array($rules) ? array_values($rules) : [$rules];
    }

    private static function ruleName(string $rule): string
    {
        $colon = strpos($rule, ':');

        return $colon === false ? $rule : substr($rule, 0, $colon);
    }

    /**
     * @return list<string>
     */
    private static function ruleArguments(string $rule): array
    {
        $colon = strpos($rule, ':');
        if ($colon === false) {
            return [];
        }

        return explode(',', substr($rule, $colon + 1));
    }
}
