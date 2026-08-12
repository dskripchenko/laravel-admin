<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\I18n;

/**
 * Translates the manifest's user-facing strings through Laravel's JSON
 * translations (`lang/{locale}.json`, keyed by the source string). It is
 * idempotent: without a translation the string comes back as it is, so a host
 * need NOT wrap its labels in `__()` — the serialization translates them
 * itself.
 */
final class Localize
{
    /**
     * The branding from config('admin.brand'), with the copyright and footer
     * localized; the name and the logo are not translated.
     *
     * @return array<string, mixed>
     */
    public static function brand(): array
    {
        $brand = (array) config('admin.brand', []);
        foreach (['copyright', 'footer'] as $key) {
            if (isset($brand[$key]) && is_string($brand[$key])) {
                $brand[$key] = self::string($brand[$key]);
            }
        }

        return $brand;
    }

    public static function string(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        return (string) __($value);
    }

    /**
     * Translates the known text keys of an attribute array — title, help,
     * placeholder, label, trueLabel, falseLabel and the labels inside options
     * — returning a copy, without mutating the original.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            $key = (string) $key;
            // Any key that names a caption: title, help, placeholder and
            // everything ending in label (label, keyLabel, trueLabel,
            // addLabel…). The list used to be fixed, and the captions of
            // key-value, repeater and tabs slipped past it.
            $isTextKey = in_array($key, ['title', 'help', 'placeholder'], true)
                || str_ends_with(mb_strtolower($key), 'label');

            if ($isTextKey && is_string($value)) {
                $attributes[$key] = self::string($value);
            } elseif (is_array($value)
                && (in_array($key, ['options', 'labels'], true)
                    || str_ends_with(mb_strtolower($key), 'options'))) {
                $attributes[$key] = self::options($value);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int|string, mixed>  $options  A list<{value, label}> or a value → label map
     * @return array<int|string, mixed>
     */
    public static function options(array $options): array
    {
        foreach ($options as $key => $option) {
            if (is_array($option) && isset($option['label']) && is_string($option['label'])) {
                $options[$key]['label'] = self::string($option['label']);
            } elseif (is_string($option)) {
                $options[$key] = self::string($option);
            }
        }

        return $options;
    }
}
