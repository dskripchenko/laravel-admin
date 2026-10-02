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

    /**
     * @param  array<string, mixed>  $replace  `:name` placeholders, as for __()
     */
    public static function string(?string $value, array $replace = []): ?string
    {
        if ($value === null || $value === '') {
            return $value;
        }

        // A source string that happens to match a translation group name
        // ("Auth", "Validation"…) resolves to that group's array: only a
        // string result counts as a translation.
        $translated = __($value, $replace);

        return is_string($translated) ? $translated : $value;
    }

    /**
     * Translates the known text keys of an attribute array — help,
     * placeholder, anything ending in title, description or label (title,
     * modalTitle, submitLabel, trueLabel…), the labels inside options and a
     * slider's marks, a builder's block labels and the titles of an
     * accordion's sections — returning a copy, without mutating the original.
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
            // So are the keys ending in title (modalTitle) and description.
            $lower = mb_strtolower($key);
            $isTextKey = in_array($key, ['help', 'placeholder'], true)
                || str_ends_with($lower, 'label')
                || str_ends_with($lower, 'title')
                || str_ends_with($lower, 'description');

            if ($isTextKey && is_string($value)) {
                $attributes[$key] = self::string($value);
            } elseif (is_array($value)
                && (in_array($key, ['options', 'labels', 'marks'], true)
                    || str_ends_with($lower, 'options'))) {
                // marks: a slider's value => caption.
                $attributes[$key] = self::options($value);
            } elseif ($key === 'blocks' && is_array($value)) {
                // A builder's block types, each with its own label.
                $attributes[$key] = array_map(
                    static fn (mixed $block): mixed => is_array($block) ? self::attributes($block) : $block,
                    $value,
                );
            } elseif ($key === 'sections' && is_array($value)) {
                // An accordion's sections: each carries its own title.
                $attributes[$key] = array_map(
                    static fn (mixed $section): mixed => is_array($section) ? self::attributes($section) : $section,
                    $value,
                );
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
