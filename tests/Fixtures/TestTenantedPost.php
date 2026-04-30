<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Tenancy\Concerns\TenantScoped;
use Dskripchenko\LaravelAdmin\Tenancy\Tenant;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant value-object для тестов.
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
 * Eloquent-модель с TenantScoped для теста фильтрации.
 *
 * @internal
 */
final class TestTenantedPost extends Model
{
    use TenantScoped;

    protected $table = 'tenanted_posts';

    protected $guarded = [];
}
