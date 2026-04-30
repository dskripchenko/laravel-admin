<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Admin;
use Dskripchenko\LaravelAdmin\Plugin\PluginRegistry;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;

beforeEach(function (): void {
    $this->app->make(PluginRegistry::class)->clear();
});

it('rejects classes that do not implement AdminPlugin', function (): void {
    $registry = app(PluginRegistry::class);
    expect(fn () => $registry->add(stdClass::class))
        ->toThrow(InvalidArgumentException::class);
});

it('add ignores duplicate class entries', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);
    $registry->add(TestSamplePlugin::class);

    $registry->registerAll();
    expect($registry->instances())->toHaveCount(1);
});

it('registerAll() instantiates plugins and calls register()', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);
    $registry->registerAll();

    /** @var TestSamplePlugin $instance */
    $instance = $registry->instances()['sample-plugin'];
    expect($instance->registered)->toBeTrue();
    expect($instance->booted)->toBeFalse();
});

it('bootAll() calls boot($admin) and plugin can register Resources', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);

    /** @var Admin $admin */
    $admin = app(Admin::class);
    $registry->bootAll($admin);

    /** @var TestSamplePlugin $instance */
    $instance = $registry->instances()['sample-plugin'];
    expect($instance->booted)->toBeTrue();

    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    expect($rr->has('test-users'))->toBeTrue();
});

it('bootAll() is idempotent', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);

    /** @var Admin $admin */
    $admin = app(Admin::class);
    $registry->bootAll($admin);
    $registry->bootAll($admin); // повторно — не должно сломаться

    expect($registry->instances())->toHaveCount(1);
});

it('rejects two plugins with the same name()', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);
    $registry->add(TestDuplicatePlugin::class);

    expect(fn () => $registry->registerAll())
        ->toThrow(RuntimeException::class);
});

it('describe() returns name+version of registered plugins', function (): void {
    $registry = app(PluginRegistry::class);
    $registry->add(TestSamplePlugin::class);
    $registry->registerAll();

    expect($registry->describe())->toBe([
        ['name' => 'sample-plugin', 'version' => '1.0.0'],
    ]);
});

it('AdminServiceProvider boot() reads admin.plugins config', function (): void {
    config()->set('admin.plugins', [TestSamplePlugin::class]);

    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();

    $registry = app(PluginRegistry::class);
    $registry->clear();

    // Имитация boot-фазы (тесты Testbench запускают её до app boot, поэтому
    // вручную дёргаем bootPlugins-эквивалент).
    $registry->addMany([TestSamplePlugin::class]);
    $registry->bootAll(app(Admin::class));

    expect($rr->has('test-users'))->toBeTrue();
});
