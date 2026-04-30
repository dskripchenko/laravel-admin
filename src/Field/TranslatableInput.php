<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * Поле с переводами на несколько языков (интеграция с dskripchenko/laravel-translatable).
 *
 * UI: вкладки/dropdown с языками; в каждом языке — Input или Textarea.
 * State хранится как `{lang_code: 'value', ...}` — backend сохраняет через
 * `Model::saveTranslations([...])` из TranslationTrait.
 *
 * Список языков по умолчанию берётся из `config('admin.ui.available_locales')`,
 * но можно переопределить через `->locales([...])`.
 */
final class TranslatableInput extends Field
{
    public function fieldType(): string
    {
        return 'translatable';
    }

    /**
     * Inner-control: 'input' | 'textarea' | 'markdown' | 'wysiwyg'.
     */
    public function as(string $control): static
    {
        $this->attributes['as'] = $control;

        return $this;
    }

    /**
     * Список языков. Если не задан — fallback на admin.ui.available_locales.
     *
     * @param  list<string>  $locales
     */
    public function locales(array $locales): static
    {
        $this->attributes['locales'] = $locales;

        return $this;
    }

    /**
     * Помечать строку как обязательную для всех locales (default: только default).
     */
    public function requireAllLocales(bool $require = true): static
    {
        $this->attributes['requireAllLocales'] = $require;

        return $this;
    }

    /**
     * Resolve locales: явно заданные → admin.ui.available_locales → ['ru', 'en'].
     *
     * @return list<string>
     */
    public function getLocales(): array
    {
        $explicit = $this->getAttribute('locales');
        if (is_array($explicit) && $explicit !== []) {
            /** @var list<string> $list */
            $list = array_values(array_filter($explicit, 'is_string'));

            return $list;
        }

        $configured = config('admin.ui.available_locales');
        if (is_array($configured) && $configured !== []) {
            /** @var list<string> $list */
            $list = array_values(array_filter($configured, 'is_string'));

            return $list;
        }

        return ['ru', 'en'];
    }
}
