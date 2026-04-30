<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;
use Dskripchenko\LaravelTranslatable\Models\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * Мост между TranslatableInput field'ом и dskripchenko/laravel-translatable.
 *
 * Используется в ResourceController.create/update: вычленяет переводимые
 * поля из payload (хранятся как `{field: {ru: 'Привет', en: 'Hello'}}`),
 * сохраняет каждую локаль через `Model::saveTranslation` из TranslationTrait.
 *
 * Сами Eloquent-модели должны подключать `TranslationTrait` чтобы это
 * работало.
 */
final class TranslatableFieldBridge
{
    /**
     * Извлекает переводимые поля из payload.
     *
     * Принимает payload как assoc array. Для каждого TranslatableInput
     * field'а берёт значение `{locale: text}` и складывает в результат.
     *
     * Изменяет $payload по reference: удаляет переводимые ключи (чтобы
     * forceFill не пытался записать их в реальные колонки модели).
     *
     * @param  list<Field>  $fields
     * @param  array<string, mixed>  $payload  in/out
     * @return array<string, array<string, string>> field => {locale => value}
     */
    public static function extract(array $fields, array &$payload): array
    {
        $extracted = [];
        foreach ($fields as $field) {
            if (! $field instanceof TranslatableInput) {
                continue;
            }
            $name = $field->name();
            if (! array_key_exists($name, $payload)) {
                continue;
            }
            $value = $payload[$name];
            unset($payload[$name]);

            if (! is_array($value)) {
                continue;
            }

            $localized = [];
            foreach ($value as $locale => $text) {
                if (! is_string($locale) || $locale === '') {
                    continue;
                }
                $localized[$locale] = is_string($text) ? $text : (string) $text;
            }
            if ($localized !== []) {
                $extracted[$name] = $localized;
            }
        }

        return $extracted;
    }

    /**
     * Сохраняет переводы через TranslationTrait::saveTranslation на model'е.
     *
     * Если model не использует TranslationTrait — silently skip (нет throw,
     * чтобы не ломать сценарий когда переводы опциональны).
     *
     * @param  array<string, array<string, string>>  $extracted  field => {locale => text}
     */
    public static function saveAll(Model $model, array $extracted): void
    {
        if (! method_exists($model, 'saveTranslation')) {
            return;
        }
        if (! class_exists(Language::class)) {
            return;
        }

        foreach ($extracted as $field => $byLocale) {
            foreach ($byLocale as $localeCode => $text) {
                $language = self::languageByCode($localeCode);
                if ($language === null) {
                    continue;
                }
                $model->saveTranslation($field, $text, $language);
            }
        }
    }

    private static function languageByCode(string $code): ?Language
    {
        return Language::query()->where('code', $code)->first();
    }
}
