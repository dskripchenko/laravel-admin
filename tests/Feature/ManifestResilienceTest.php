<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Support\GlobalSearch;
use Dskripchenko\LaravelAdmin\Support\Manifest;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * One broken resource must not take the panel down: it is logged and left
 * out of the manifest, the menu and the global search, and with app.debug on
 * the manifest names it under `diagnostics` for the administrator.
 */
final class ResilienceWidget extends Model
{
    protected $table = 'resilience_widgets';

    protected $guarded = [];
}

final class ResilienceHealthyResource extends Resource
{
    public static string $model = ResilienceWidget::class;

    public static function slug(): string
    {
        return 'resilience-healthy';
    }

    public function fields(): array
    {
        return [Input::make('name')];
    }

    public function searchableFields(): array
    {
        return ['name'];
    }
}

/** Throws while its schema is described. */
final class ResilienceBrokenFieldsResource extends Resource
{
    public static string $model = ResilienceWidget::class;

    public static function slug(): string
    {
        return 'resilience-broken-fields';
    }

    public function fields(): array
    {
        throw new RuntimeException('Call to undefined method TableColumn::sortable()');
    }
}

/** Throws as soon as it is built. */
final class ResilienceBrokenConstructorResource extends Resource
{
    public static string $model = ResilienceWidget::class;

    public function __construct()
    {
        throw new LogicException('The constructor exploded');
    }

    public static function slug(): string
    {
        return 'resilience-broken-constructor';
    }

    public function fields(): array
    {
        return [];
    }
}

beforeEach(function (): void {
    Schema::create('resilience_widgets', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->timestamps();
    });
    ResilienceWidget::query()->create(['name' => 'Gizmo']);

    $registry = app(ResourceRegistry::class);
    $registry->clear();
    $registry->add(ResilienceHealthyResource::class);
    $registry->add(ResilienceBrokenFieldsResource::class);
    $registry->add(ResilienceBrokenConstructorResource::class);
    AdminApi::clearCache();
    app(Manifest::class)->flush();

    $admin = AdminUser::create([
        'name' => 'Resilience Admin',
        'email' => 'resilience-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $admin->assignRole(Role::create(['name' => 'Super', 'slug' => 'res-super-'.uniqid(), 'permissions' => ['*']]));
    $this->actingAs($admin->refresh(), 'admin');
});

it('leaves a throwing resource out of the manifest and logs it, instead of failing the panel', function (): void {
    config(['app.debug' => false]);
    Log::spy();

    $response = $this->getJson('/api/admin/system/manifest')->assertOk();

    expect(collect($response->json('payload.resources'))->pluck('slug')->all())->toBe(['resilience-healthy']);
    // Nothing for the administrator outside debug mode.
    expect($response->json('payload.diagnostics'))->toBe([]);

    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[resilience-broken-fields]') && str_contains($message, 'sortable()'),
    )->once();
    Log::shouldHaveReceived('error')->withArgs(
        fn (string $message): bool => str_contains($message, '[resilience-broken-constructor]'),
    )->once();
});

it('names the skipped resources under diagnostics in debug mode', function (): void {
    config(['app.debug' => true]);

    $diagnostics = $this->getJson('/api/admin/system/manifest')->assertOk()->json('payload.diagnostics');

    expect(collect($diagnostics)->pluck('slug')->all())
        ->toBe(['resilience-broken-fields', 'resilience-broken-constructor']);
    expect($diagnostics[0])
        ->toMatchArray(['kind' => 'resource', 'class' => ResilienceBrokenFieldsResource::class])
        ->and($diagnostics[0]['message'])->toContain('resilience-broken-fields')->toContain('sortable()');
});

it('keeps the menu, the healthy routes and the global search working around a throwing resource', function (): void {
    // The routes are compiled per request: the healthy resource keeps its own.
    $this->postJson('/api/admin/resilience-healthy/search')->assertOk()->assertJsonCount(1, 'payload.data');
    expect($this->postJson('/api/admin/resilience-broken-fields/search')->status())->not->toBe(500);

    $items = $this->getJson('/api/admin/system/menu')->assertOk()->json('payload.items');
    $keys = collect($items)->pluck('key')->all();
    expect($keys)->toContain('resilience-healthy')
        ->not->toContain('resilience-broken-constructor');

    $groups = app(GlobalSearch::class)->search('giz');
    expect(collect($groups)->pluck('slug')->all())->toBe(['resilience-healthy']);
});
