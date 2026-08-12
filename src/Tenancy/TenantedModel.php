<?php

declare(strict_types=1);

namespace Dskripchenko\LaravelAdmin\Tenancy;

use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;
use Illuminate\Database\Eloquent\Model;

/**
 * An optional abstract base model with TenantScoped already applied.
 *
 * It is shorthand for a host project:
 *
 *     class Post extends TenantedModel { ... }
 *
 * which is the same as:
 *
 *     class Post extends Model { use TenantScoped; ... }
 *
 * When other traits are needed — HasFactory, Loggable — it is better to use
 * TenantScoped directly rather than to inherit from this class.
 */
abstract class TenantedModel extends Model
{
    use TenantScoped;
}
