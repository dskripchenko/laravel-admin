<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Settings\Storage;

/**
 * Контракт хранилища settings.
 *
 * Реализации: KeyValueSettingsStorage (admin_settings таблица),
 * EloquentSettingsStorage (отдельная Eloquent-модель с типизированными колонками).
 *
 * Все методы работают с `group` (namespace) — обычно совпадает со slug'ом
 * SettingsResource'а.
 */
interface SettingsStorage
{
    /**
     * Получить все settings из группы.
     *
     * @return array<string, mixed>
     */
    public function all(string $group): array;

    /**
     * Получить одно значение.
     */
    public function get(string $group, string $key, mixed $default = null): mixed;

    /**
     * Сохранить bulk-обновление группы. Старые ключи, не упомянутые в $values,
     * остаются нетронутыми (merge-семантика).
     *
     * @param  array<string, mixed>  $values
     */
    public function save(string $group, array $values): void;

    /**
     * Полный ре-set группы — удаляет старые ключи и записывает новые.
     *
     * @param  array<string, mixed>  $values
     */
    public function replace(string $group, array $values): void;

    /**
     * Удалить одно значение.
     */
    public function forget(string $group, string $key): void;
}
