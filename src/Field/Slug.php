<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A URL slug generated from a source field.
 *
 * The SPA fills it as the source changes, until the slug is edited by hand
 * (clearing it hands it back to the source). The conversion is generate():
 * the SPA runs a port of it over the same transliteration table, so the slug
 * typed for you is the one the server would produce — Cyrillic, Greek and
 * Latin letters with diacritics included.
 */
final class Slug extends Field
{
    /** The transliteration table shared with the SPA's slugify(). */
    public const TRANSLITERATION_TABLE = __DIR__.'/../../resources/ts/components/fields/support/transliteration.json';

    /** @var array<string, string>|null */
    private static ?array $map = null;

    public function fieldType(): string
    {
        return 'slug';
    }

    /**
     * The name of the other field, in the same form, the slug is generated from.
     */
    public function from(string $sourceField): static
    {
        $this->attributes['from'] = $sourceField;

        return $this;
    }

    public function separator(string $separator): static
    {
        $this->attributes['separator'] = $separator;

        return $this;
    }

    /**
     * Whether to follow every change of the source field; true by default.
     *
     * With true, a slug that still matches its source — on a new record, or
     * on a saved one whose slug was never customised — follows the source
     * until it is edited by hand. With false the slug is filled only when it
     * is empty: on a new record it follows the source until edited, a saved
     * record's slug is never rewritten.
     *
     * It is serialized as the `follow` attribute, apart from the `reactive`
     * one visibleWhen() uses.
     */
    public function reactive(bool $reactive = true): static
    {
        $this->attributes['follow'] = $reactive;

        return $this;
    }

    /**
     * Generates a slug from a string. Each character is lower-cased on its
     * own; then every character is transliterated by the shared table, kept
     * when it is a-z or 0-9, a word break when it is whitespace, a dash, `_`
     * or the separator, `at` when it is `@`, and dropped otherwise. The words
     * are joined with the separator.
     */
    public static function generate(string $source, string $separator = '-'): string
    {
        $map = self::map();
        $out = '';
        // Lower-casing one character may give two ('İ' is 'i' and a combining
        // dot), so the lower-cased text is walked again.
        $lower = implode('', array_map(mb_strtolower(...), mb_str_split($source)));
        foreach (mb_str_split($lower) as $char) {
            if (isset($map[$char])) {
                $out .= $map[$char];
            } elseif (preg_match('/^[a-z0-9]$/', $char) === 1) {
                $out .= $char;
            } elseif ($char === '@') {
                $out .= ' at ';
            } elseif (self::isBreak($char) || ($separator !== '' && $char === $separator)) {
                $out .= ' ';
            }
        }

        return implode($separator, array_values(array_filter(
            explode(' ', $out),
            static fn (string $word): bool => $word !== '',
        )));
    }

    private static function isBreak(string $char): bool
    {
        if (in_array($char, [' ', "\t", "\n", "\v", "\f", "\r", '-', '_'], true)) {
            return true;
        }
        $code = mb_ord($char);

        return in_array($code, [0x00A0, 0x1680, 0x2028, 0x2029, 0x202F, 0x205F, 0x3000, 0x2212], true)
            || ($code >= 0x2000 && $code <= 0x200A)
            || ($code >= 0x2010 && $code <= 0x2015);
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        if (self::$map === null) {
            /** @var array{map?: array<string, string>} $table */
            $table = json_decode((string) file_get_contents(self::TRANSLITERATION_TABLE), true, 512, JSON_THROW_ON_ERROR);
            self::$map = $table['map'] ?? [];
        }

        return self::$map;
    }
}
