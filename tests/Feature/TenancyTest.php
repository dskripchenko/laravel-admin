<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Tenancy\SingleTenantResolver;
use Dskripchenko\LaravelAdmin\Tenancy\TenantContext;
use Dskripchenko\LaravelAdmin\Tenancy\TenantResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('tenanted_posts', function (Blueprint $t): void {
        $t->id();
        $t->unsignedInteger('tenant_id')->index();
        $t->string('title')->nullable();
        $t->timestamps();
    });

    /** @var TenantResolver $resolver */
    $resolver = app(TenantResolver::class);
    $resolver->setCurrent(null);
});

it('SingleTenantResolver default current = null', function (): void {
    expect(app(TenantResolver::class)->current())->toBeNull();
});

it('SingleTenantResolver::setCurrent stores and returns', function (): void {
    $tenant = new TestTenant(42, 'Acme');
    /** @var SingleTenantResolver $resolver */
    $resolver = app(TenantResolver::class);
    $resolver->setCurrent($tenant);

    expect($resolver->current())->toBe($tenant);
    expect($resolver->available())->toBe([$tenant]);
});

it('TenantContext::currentKey returns tenant key or null', function (): void {
    $context = app(TenantContext::class);
    expect($context->currentKey())->toBeNull();

    app(TenantResolver::class)->setCurrent(new TestTenant(7, 'X'));
    expect($context->currentKey())->toBe(7);
});

it('TenantContext::withTenant runs callback in tenant context and restores', function (): void {
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $a = new TestTenant(1, 'A');
    $b = new TestTenant(2, 'B');
    app(TenantResolver::class)->setCurrent($a);

    $captured = $context->withTenant($b, function () use ($context): int {
        /** @var int $key */
        $key = $context->currentKey();

        return $key;
    });

    expect($captured)->toBe(2);
    expect($context->currentKey())->toBe(1); // восстановлено
});

it('TenantScoped: creating без tenant_id auto-fills из текущего tenant', function (): void {
    app(TenantResolver::class)->setCurrent(new TestTenant(7, 'A'));
    $post = TestTenantedPost::create(['title' => 'Hello']);
    expect((int) $post->tenant_id)->toBe(7);
});

it('TenantScoped: explicit tenant_id NOT overridden', function (): void {
    app(TenantResolver::class)->setCurrent(new TestTenant(7, 'A'));
    $post = TestTenantedPost::create(['title' => 'X', 'tenant_id' => 99]);
    expect((int) $post->tenant_id)->toBe(99);
});

it('TenantScope filters queries by current tenant', function (): void {
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $context->withTenant(new TestTenant(1, 'A'), fn () => TestTenantedPost::create(['title' => 'A1']));
    $context->withTenant(new TestTenant(2, 'B'), fn () => TestTenantedPost::create(['title' => 'B1']));

    app(TenantResolver::class)->setCurrent(new TestTenant(1, 'A'));
    $titles = TestTenantedPost::pluck('title')->all();
    expect($titles)->toBe(['A1']);

    app(TenantResolver::class)->setCurrent(new TestTenant(2, 'B'));
    expect(TestTenantedPost::pluck('title')->all())->toBe(['B1']);
});

it('TenantScope: current tenant null → no filtering (single-tenant mode)', function (): void {
    /** @var TenantContext $context */
    $context = app(TenantContext::class);
    $context->withTenant(new TestTenant(1, 'A'), fn () => TestTenantedPost::create(['title' => 'X1']));
    $context->withTenant(new TestTenant(2, 'B'), fn () => TestTenantedPost::create(['title' => 'X2']));

    // resolver current = null после beforeEach
    expect(TestTenantedPost::count())->toBe(2);
});

it('TenantScoped::creating без current tenant — tenant_id остаётся NULL', function (): void {
    // Делаем колонку nullable для этого теста.
    Schema::table('tenanted_posts', function (Blueprint $t): void {
        $t->unsignedInteger('tenant_id')->nullable()->change();
    });

    $post = TestTenantedPost::create(['title' => 'Orphan']);
    expect($post->tenant_id)->toBeNull();
});
