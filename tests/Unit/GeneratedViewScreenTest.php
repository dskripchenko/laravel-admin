<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Resource\Screens\GeneratedViewScreen;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($t): void {
            $t->id();
            $t->string('name')->nullable();
            $t->string('email')->nullable();
            $t->string('password')->nullable();
            $t->timestamps();
        });
    }
});

it('GeneratedViewScreen has type=generated.view', function (): void {
    $s = new GeneratedViewScreen(new TestUserResource);
    $compiled = $s->compile();

    expect($compiled['type'])->toBe('generated.view');
    expect($compiled['resource_slug'])->toBe('test-users');
});

it('GeneratedViewScreen loads record via query($id)', function (): void {
    $r = TestResourceUserModel::create(['name' => 'V', 'email' => 'v@example.com', 'password' => 'x']);
    $s = new GeneratedViewScreen(new TestUserResource);
    $compiled = $s->compile($r->id);

    expect($compiled['state']['record']['name'])->toBe('V');
    expect($compiled['state']['id'])->toBe($r->id);
});

it('GeneratedViewScreen 404 when record missing', function (): void {
    $s = new GeneratedViewScreen(new TestUserResource);
    expect(fn () => $s->compile(99999))
        ->toThrow(Symfony\Component\HttpKernel\Exception\NotFoundHttpException::class);
});

it('GeneratedViewScreen layout returns Infolist', function (): void {
    $s = new GeneratedViewScreen(new TestUserResource);
    $compiled = $s->compile();
    expect($compiled['layout'])->toHaveCount(1);
    expect($compiled['layout'][0]['type'])->toBe('infolist');
});

it('GeneratedViewScreen permission = resource.view', function (): void {
    $s = new GeneratedViewScreen(new TestUserResource);
    expect($s->permission())->toBe('admin.test-users.view');
});

it('Resource::infolist defaults to TextEntry per field', function (): void {
    $resource = new TestUserResource;
    $entries = $resource->infolist();

    // 3 поля в TestUserResource: name, email, password.
    expect($entries)->toHaveCount(3);
    foreach ($entries as $e) {
        expect($e->toArray()['type'])->toBe('text');
    }
});

it('ResourceManifest::describe includes view screen', function (): void {
    $description = Dskripchenko\LaravelAdmin\Resource\ResourceManifest::describe(new TestUserResource);

    expect($description['screens'])->toHaveKey('view');
    expect($description['screens']['view']['slug'])->toBe('test-users.view');
    expect($description['screens']['view']['type'])->toBe('generated.view');
    expect($description['screens']['view']['permission'])->toBe('admin.test-users.view');
});
