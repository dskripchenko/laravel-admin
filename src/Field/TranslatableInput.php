<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

/**
 * A field translated into several languages, integrating with
 * dskripchenko/laravel-translatable.
 *
 * The UI is a set of tabs or a dropdown of languages, each holding an input or
 * a textarea. The state is `{lang_code: 'value', ...}`, and the backend saves
 * it through `Model::saveTranslations([...])` from TranslationTrait.
 *
 * The languages come from `config('admin.ui.available_locales')` by default
 * and can be overridden with `->locales([...])`.
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
     * The languages; without them, admin.ui.available_locales is used.
     *
     * @param  list<string>  $locales
     */
    public function locales(array $locales): static
    {
        $this->attributes['locales'] = $locales;

        return $this;
    }

    /**
     * Marks the string required in every locale; by default only the default one.
     */
    public function requireAllLocales(bool $require = true): static
    {
        $this->attributes['requireAllLocales'] = $require;

        return $this;
    }

    /**
     * Resolves the locales: the explicit ones → admin.ui.available_locales → ['ru', 'en'].
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
