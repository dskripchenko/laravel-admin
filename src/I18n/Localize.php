<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\I18n;

/**
 * Перевод пользовательских строк манифеста через JSON-переводы Laravel
 * (`lang/{locale}.json`, ключ — исходная строка). Идемпотентно: без перевода
 * строка возвращается как есть, поэтому host может НЕ оборачивать лейблы в
 * `__()` — сериализация переводит их сама (BL-11).
 */
final class Localize
{
    /**
     * Брендинг из config('admin.brand') с локализованным copyright/footer
     * (name/logo не переводятся).
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
     * Перевести известные текстовые ключи массива атрибутов (копия, без
     * мутации исходника): title/help/placeholder/label/trueLabel/falseLabel
     * + labels в options.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function attributes(array $attributes): array
    {
        foreach ($attributes as $key => $value) {
            $key = (string) $key;
            // Любой ключ-подпись: title/help/placeholder и всё, что
            // заканчивается на label (label, keyLabel, trueLabel, addLabel…) —
            // раньше список был жёстким и мимо него проходили подписи
            // key-value/repeater/tabs.
            $isTextKey = in_array($key, ['title', 'help', 'placeholder'], true)
                || str_ends_with(mb_strtolower($key), 'label');

            if ($isTextKey && is_string($value)) {
                $attributes[$key] = self::string($value);
            } elseif (in_array($key, ['options', 'labels'], true) && is_array($value)) {
                $attributes[$key] = self::options($value);
            }
        }

        return $attributes;
    }

    /**
     * @param  array<int|string, mixed>  $options  list<{value, label}> либо мапа value→label
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
