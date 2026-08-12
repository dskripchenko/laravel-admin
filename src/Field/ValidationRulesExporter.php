<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Turns a list of Field objects into Laravel-style validation rules fit for
 * `Request::validate(...)`.
 *
 * It works in two stages:
 *   1. It takes the explicitly declared `$field->getRules()`.
 *   2. It fills them out with type-specific implicit rules:
 *      - number/slider → `numeric`, `integer` (when ->integer()), `min:`/`max:` from the attributes
 *      - an email input (->type('email')) → `email`
 *      - date/date_range/time → `date` or `date_format`
 *      - file → `file`/`image`, `mimes:`, `max:` (KB), `array` for multiple, `between:0,maxFiles`
 *      - select/checkbox/radio with multiple → `array`
 *      - color → `regex:/^#?[0-9a-f]{3,8}$/i` for hex
 *
 * The point is that Resource::validationRules() should not have to repeat the
 * limits by hand on both the UI side and the backend side.
 */
final class ValidationRulesExporter
{
    /**
     * @param  list<Field>  $fields
     * @param  string  $context  create|update|view — filters by appliesTo()
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
                // The field has to reach validate(), otherwise Laravel drops
                // it from $data — even when the backend wants the value as it
                // is. The default is `nullable`, with no explicit limits.
                $rules = ['nullable'];
            }
            $result[$field->name()] = $rules;

            // File fields: the SPA is upload-first (FileField.vue uploads
            // through /uploads/upload and puts {disk, path, ...} into the
            // form), so that shape of the value needs a contract.
            if ($field->fieldType() === 'file') {
                $prefix = ($field->getAttribute('multiple') ?? false) === true
                    ? $field->name().'.*'
                    : $field->name();
                $result[$prefix.'.disk'] = ['required_with:'.$prefix, 'string'];
                $result[$prefix.'.path'] = ['required_with:'.$prefix, 'string'];
            }
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
        // Object rules (Rule::unique() and friends) go into the validator as
        // they are — the exporter used to drop them silently, so they did not
        // work at all.
        $objects = array_values(array_filter($all, static fn ($r): bool => ! is_string($r)));
        $implicit = self::implicitRulesByType($field);

        // The required attribute pulls in the rule by itself, in case
        // rules([...]) overwrote the array after required().
        if (($field->getAttribute('required') ?? false) === true
            && ! in_array('required', $explicit, true)) {
            $explicit[] = 'required';
        }

        // The explicit rules win; we add only the implicit ones that do not repeat a prefix.
        $merged = $explicit;
        foreach ($implicit as $rule) {
            if (! self::ruleAlreadyApplied($explicit, $rule)) {
                $merged[] = $rule;
            }
        }

        return [...array_unique($merged), ...$objects];
    }

    /**
     * Out of the mixed rules we take only the string ones. Object and array
     * rules are skipped here — they go to Laravel directly through rules().
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

            // The array's elements are checked too — in Laravel that goes
            // through name.*, while the exporter here returns the rules for
            // name itself. A per-element rule is added separately by whoever
            // needs one.
            return $rules;
        }

        // Upload-first: the SPA never sends multipart on create or update —
        // the file has already gone through /uploads/upload and the form
        // carries {disk, path}. The `file` and `mimes` rules rejected exactly
        // the shape of value the panel sends, so creating a record with a file
        // field could not pass validation at all (found by printable's
        // OptionsIntegrityTest on font files). The size and the type are
        // checked during the upload itself (/uploads/upload) and by fillModel's
        // domain logic.
        $rules[] = 'array';

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
     * Tells whether a rule with the same prefix (`min:`, `max:`) is already there.
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
}
