<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\I18n;

use Illuminate\Support\Str;

/**
 * The label of a column, a filter, a field or an entry declared without one,
 * made from its name.
 *
 * The common column names get a caption of the panel's source language, which
 * Localize then translates like any other — `created_at` reads "Создано" in a
 * Russian panel and "Created" in an English one. Any other name is made
 * readable as it is (`opens_at` → "Opens at"), which a host translates through
 * its own JSON dictionary or replaces with an explicit label.
 */
final class AutoLabel
{
    /** @var array<string, string> name => caption in the source language */
    private const COMMON = [
        'id' => 'ID',
        'created_at' => 'Создано',
        'updated_at' => 'Обновлено',
        'deleted_at' => 'Удалено',
        'published_at' => 'Опубликовано',
        'title' => 'Заголовок',
        'description' => 'Описание',
        'status' => 'Статус',
        'type' => 'Тип',
        'locale' => 'Язык',
        'is_active' => 'Активен',
        'password' => 'Пароль',
        'phone' => 'Телефон',
        'position' => 'Позиция',
        'ip' => 'IP',
        'user_agent' => 'User-Agent',
    ];

    /**
     * The caption of a common column name, or null.
     */
    public static function common(string $name): ?string
    {
        return self::COMMON[$name] ?? null;
    }

    /**
     * "created at" style: the first letter capitalised — columns and filters.
     */
    public static function sentence(string $name): string
    {
        return self::common($name) ?? ucfirst(trim(str_replace(['_', '.'], ' ', $name)));
    }

    /**
     * "Created At" style: every word capitalised — fields and entries.
     */
    public static function headline(string $name): string
    {
        if ($name === '') {
            return '';
        }

        return self::common($name) ?? Str::headline(str_replace('.', ' ', $name));
    }
}
