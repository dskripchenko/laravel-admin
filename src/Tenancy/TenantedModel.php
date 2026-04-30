<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * Опциональная abstract base-модель с подключённым TenantScoped.
 *
 * Используется для краткой записи в host-проекте:
 *
 *     class Post extends TenantedModel { ... }
 *
 * Эквивалент:
 *
 *     class Post extends Model { use TenantScoped; ... }
 *
 * Если нужны другие трейты (HasFactory, Loggable) — лучше подключать
 * TenantScoped напрямую без наследования от этого класса.
 */
abstract class TenantedModel extends Model
{
    use TenantScoped;
}
