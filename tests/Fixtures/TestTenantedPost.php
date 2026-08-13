<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * A tenant value object for the tests.
 *
 * @internal
 */
final class TestTenant implements Tenant
{
    public function __construct(
        private readonly int $id,
        private readonly string $label,
    ) {}

    public function getTenantKey(): int|string
    {
        return $this->id;
    }

    public function getTenantLabel(): string
    {
        return $this->label;
    }
}

/**
 * An Eloquent model with TenantScoped, for the filtering test.
 *
 * @internal
 */
final class TestTenantedPost extends Model
{
    use TenantScoped;

    protected $table = 'tenanted_posts';

    protected $guarded = [];
}
