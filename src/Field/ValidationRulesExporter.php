<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Преобразует список Field-объектов в Laravel-style validation rules,
 * применимые в `Request::validate(...)`.
 *
 * Работает в две стадии:
 *   1. Берёт явно объявленные `$field->getRules()`.
 *   2. Дополняет их type-specific implicit-rules:
 *      - number/slider → `numeric`, `integer` (если ->integer()), `min:`/`max:` из attributes
 *      - email-input (->type('email')) → `email`
 *      - date/date_range/time → `date` или `date_format`
 *      - file → `file`/`image`, `mimes:`, `max:` (KB), `array` для multiple, `between:0,maxFiles`
 *      - select/checkbox/radio с multiple → `array`
 *      - color → `regex:/^#?[0-9a-f]{3,8}$/i` (для hex)
 *
 * Цель — чтобы Resource::validationRules() не требовал ручного дублирования
 * limits на UI-side и backend-side.
 */
final class ValidationRulesExporter
{
    /**
     * @param  list<Field>  $fields
     * @param  string  $context  create|update|view — фильтрует по appliesTo()
     * @return array<string, list<mixed>>
     */
    public static function export(array $fields, string $context = 'create'): array
    {
        $result = [];
        foreach ($fields as $field) {
            if (! $field->appliesTo($context)) {
                continue;
            }
            $rules = self::rulesFor($field);
            if ($rules === []) {
                // Поле должно попасть в validate(), иначе Laravel срежет
                // его из $data — даже если бэкенд хочет получить значение
                // как-есть. Default — `nullable` (без явных ограничений).
                $rules = ['nullable'];
            }
            $result[$field->name()] = $rules;
        }

        return $result;
    }

    /**
     * @return list<mixed>
     */
    private static function rulesFor(Field $field): array
    {
        $all = $field->getRules();
        $explicit = self::onlyStringRules($all);
        // Object-rules (Rule::unique() и т.п.) идут в валидатор как есть —
        // раньше экспортёр молча их отбрасывал и они не работали вовсе.
        $objects = array_values(array_filter($all, static fn ($r): bool => ! is_string($r)));
        $implicit = self::implicitRulesByType($field);

        // required attribute сам по себе подтягивает rule (на случай если
        // rules([...]) перетёрли массив после required()).
        if (($field->getAttribute('required') ?? false) === true
            && ! in_array('required', $explicit, true)) {
            $explicit[] = 'required';
        }

        // explicit имеет приоритет; добавляем только те implicit, что не дублируют префикс.
        $merged = $explicit;
        foreach ($implicit as $rule) {
            if (! self::ruleAlreadyApplied($explicit, $rule)) {
                $merged[] = $rule;
            }
        }

        return [...array_unique($merged), ...$objects];
    }

    /**
     * Из mixed-rules берём только string-rules. Object/array-rules экспортёр
     * пропускает — они едут в Laravel напрямую через rules().
     *
     * @param  list<string|array<string, mixed>>  $rules
     * @return list<string>
     */
    private static function onlyStringRules(array $rules): array
    {
        return array_values(array_filter($rules, 'is_string'));
    }

    /**
     * @return list<string>
     */
    private static function implicitRulesByType(Field $field): array
    {
        $attrs = $field->getAttributes();

        return match ($field->fieldType()) {
            'number', 'slider' => self::numericRules($attrs),
            'input' => self::inputRules($attrs),
            'date' => ['date'],
            'date_range' => ['array'],
            'time' => self::timeRules($attrs),
            'file' => self::fileRules($attrs),
            'select', 'combobox', 'checkbox', 'radio' => self::choiceRules($attrs),
            'color' => self::colorRules($attrs),
            'wysiwyg', 'markdown', 'textarea', 'code' => ['nullable', 'string'],
            'switch', 'switcher', 'boolean' => ['nullable', 'boolean'],
            'repeater', 'key-value', 'tags' => ['nullable', 'array'],
            default => ['nullable'],
        };
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function numericRules(array $attrs): array
    {
        $rules = [($attrs['integer'] ?? false) === true ? 'integer' : 'numeric'];

        if (isset($attrs['min'])) {
            $rules[] = 'min:'.$attrs['min'];
        }
        if (isset($attrs['max'])) {
            $rules[] = 'max:'.$attrs['max'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function inputRules(array $attrs): array
    {
        $rules = [];
        $htmlType = $attrs['type'] ?? null;

        if ($htmlType === 'email') {
            $rules[] = 'email';
        }
        if ($htmlType === 'url') {
            $rules[] = 'url';
        }
        if (isset($attrs['maxlength'])) {
            $rules[] = 'max:'.$attrs['maxlength'];
        }
        if (isset($attrs['minlength'])) {
            $rules[] = 'min:'.$attrs['minlength'];
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function timeRules(array $attrs): array
    {
        $format = $attrs['format'] ?? 'H:i';

        return ['date_format:'.$format];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function fileRules(array $attrs): array
    {
        $rules = [];

        if (($attrs['multiple'] ?? false) === true) {
            $rules[] = 'array';
            if (isset($attrs['maxFiles'])) {
                $rules[] = 'max:'.$attrs['maxFiles'];
            }

            // Каждый элемент массива тоже проверяем — это идёт через name.* в Laravel,
            // но здесь экспортёр возвращает rules для name напрямую. Каждый-элемент
            // правило ставит реализатор отдельно при необходимости.
            return $rules;
        }

        $rules[] = ($attrs['image'] ?? false) === true ? 'image' : 'file';

        if (isset($attrs['maxSize'])) {
            $rules[] = 'max:'.$attrs['maxSize'];
        }
        if (isset($attrs['accept']) && is_string($attrs['accept'])) {
            $exts = self::extensionsFromAccept($attrs['accept']);
            if ($exts !== []) {
                $rules[] = 'mimes:'.implode(',', $exts);
            }
        }

        return $rules;
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function choiceRules(array $attrs): array
    {
        if (($attrs['multiple'] ?? false) === true) {
            return ['nullable', 'array'];
        }

        return ['nullable'];
    }

    /**
     * @param  array<string, mixed>  $attrs
     * @return list<string>
     */
    private static function colorRules(array $attrs): array
    {
        $format = $attrs['format'] ?? 'hex';

        return $format === 'hex'
            ? ['regex:/^#?[0-9a-f]{3,8}$/i']
            : [];
    }

    /**
     * Проверяет, есть ли уже правило с тем же префиксом (`min:`, `max:`).
     *
     * @param  list<string>  $existing
     */
    private static function ruleAlreadyApplied(array $existing, string $candidate): bool
    {
        $candidatePrefix = self::rulePrefix($candidate);
        if ($candidatePrefix === '') {
            return in_array($candidate, $existing, true);
        }

        foreach ($existing as $rule) {
            if (str_starts_with($rule, $candidatePrefix.':') || $rule === $candidatePrefix) {
                return true;
            }
        }

        return false;
    }

    private static function rulePrefix(string $rule): string
    {
        $colon = strpos($rule, ':');

        return $colon === false ? $rule : substr($rule, 0, $colon);
    }

    /**
     * Парсит accept-строку (`image/*,.pdf,application/json`) в список расширений
     * для Laravel `mimes:`.
     *
     * @return list<string>
     */
    private static function extensionsFromAccept(string $accept): array
    {
        $parts = array_filter(array_map('trim', explode(',', $accept)));
        $exts = [];

        foreach ($parts as $part) {
            if (str_starts_with($part, '.')) {
                $exts[] = ltrim($part, '.');

                continue;
            }
            if (str_contains($part, '/') && ! str_ends_with($part, '/*')) {
                // application/pdf → pdf
                $sub = substr($part, strpos($part, '/') + 1);
                $exts[] = $sub;
            }
        }

        return array_values(array_unique($exts));
    }
}
