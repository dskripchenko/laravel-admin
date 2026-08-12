<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Theme;

use Dskripchenko\LaravelAdmin\Field\Field;
use Dskripchenko\LaravelAdmin\Field\TranslatableInput;
use Dskripchenko\LaravelTranslatable\Models\Language;
use Illuminate\Database\Eloquent\Model;

/**
 * The bridge between the TranslatableInput field and
 * dskripchenko/laravel-translatable.
 *
 * ResourceController's create and update use it: it pulls the translatable
 * fields out of the payload, where they sit as
 * `{field: {ru: 'Привет', en: 'Hello'}}`, and saves each locale through
 * TranslationTrait's `Model::saveTranslation`.
 *
 * The Eloquent models themselves have to use `TranslationTrait` for any of
 * this to work.
 */
final class TranslatableFieldBridge
{
    /**
     * Extracts the translatable fields from a payload.
     *
     * It takes the payload as an associative array and, for every
     * TranslatableInput field, moves the `{locale: text}` value into the
     * result.
     *
     * $payload is modified by reference: the translatable keys are removed, so
     * that forceFill does not try to write them into real columns of the
     * model.
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
     * Saves the translations through the model's
     * TranslationTrait::saveTranslation.
     *
     * A model that does not use TranslationTrait is skipped silently — nothing
     * is thrown, so that the cases where translations are optional keep
     * working.
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
