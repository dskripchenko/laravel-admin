<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Field;

use Illuminate\Support\Str;

/**
 * URL-slug с auto-генерацией из source-поля.
 *
 * SPA делает auto-update: при изменении source применяет `Str::slug` и пишет
 * в state этого поля (если slug ещё не редактировался вручную). Backend
 * `generate()` делает то же преобразование на стороне сервера для тестов и
 * non-SPA сценариев.
 */
final class Slug extends Field
{
    public function fieldType(): string
    {
        return 'slug';
    }

    /**
     * Имя другого Field'а в той же форме, из которого генерируется slug.
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
     * Авто-обновление при каждом изменении source-поля (default true).
     * Если false — только при первом set'е, потом slug «отрывается» от source.
     */
    public function reactive(bool $reactive = true): static
    {
        $this->attributes['reactive'] = $reactive;

        return $this;
    }

    /**
     * Сгенерировать slug из строки. Используется в backend-сценариях
     * (тесты / non-SPA fallback).
     */
    public static function generate(string $source, string $separator = '-'): string
    {
        return Str::slug($source, $separator);
    }
}
