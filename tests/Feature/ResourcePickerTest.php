<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Field\ResourcePicker;
use Dskripchenko\LaravelAdmin\Field\Rules\ResourceRecordsExist;
use Dskripchenko\LaravelAdmin\Field\ValidationRulesExporter;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Infolist\FieldEntry;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestPickerPhotoResource::class);
    $rr->add(TestPickerPostResource::class);
    AdminApi::clearCache();

    Schema::create('picker_photos', function (Blueprint $t): void {
        $t->id();
        $t->string('name');
        $t->string('album')->nullable();
        $t->boolean('private')->default(false);
        $t->timestamps();
    });
    Schema::create('picker_posts', function (Blueprint $t): void {
        $t->id();
        $t->string('title')->nullable();
        $t->unsignedBigInteger('cover_id')->nullable();
        $t->json('gallery')->nullable();
        $t->timestamps();
    });

    $this->actAs = function (array $permissions): void {
        $admin = AdminUser::create([
            'name' => 'Picker',
            'email' => 'p-'.uniqid().'@example.com',
            'password' => 'secret',
        ]);
        $role = Role::create(['name' => 'P', 'slug' => 'p-'.uniqid(), 'permissions' => $permissions]);
        $admin->assignRole($role);
        $this->actingAs($admin->refresh(), 'admin');
    };
});

it('serializes the target, the mode and the permissions', function (): void {
    $field = ResourcePicker::make('gallery')
        ->resource(TestPickerPhotoResource::class)
        ->multiple()
        ->maxItems(5)
        ->filters(['album' => 'covers'])
        ->perPage(12)
        ->layout('grid')
        ->uploadTo('/photos/upload', responseKey: 'photo', data: ['album' => 'covers'])
        ->toArray();

    expect($field['type'])->toBe('resource_picker')
        ->and($field['attributes']['resource'])->toBe('test-picker-photos')
        ->and($field['attributes']['multiple'])->toBeTrue()
        ->and($field['attributes']['maxItems'])->toBe(5)
        ->and($field['attributes']['filters'])->toBe(['album' => 'covers'])
        ->and($field['attributes']['perPage'])->toBe(12)
        ->and($field['attributes']['layout'])->toBe('grid')
        ->and($field['attributes']['viewPermission'])->toBe('admin.test-picker-photos.view')
        ->and($field['attributes']['upload'])->toMatchArray([
            'url' => '/photos/upload',
            'permission' => 'admin.test-picker-photos.create',
            'fileField' => 'file',
            'responseKey' => 'photo',
            'data' => ['album' => 'covers'],
        ]);
});

it('is single by default and keeps a slug of an unregistered resource as given', function (): void {
    $field = ResourcePicker::make('cover_id')->resource('elsewhere')->toArray();

    expect($field['attributes']['multiple'])->toBeFalse()
        ->and($field['attributes']['resource'])->toBe('elsewhere')
        ->and($field['attributes']['viewPermission'])->toBeNull();
});

it('exports the rules of both modes with the existence check', function (): void {
    $rules = ValidationRulesExporter::export((new TestPickerPostResource)->fields());

    expect(array_filter($rules['cover_id'], 'is_string'))->toBe(['nullable'])
        ->and($rules['cover_id'][1])->toBeInstanceOf(ResourceRecordsExist::class)
        ->and(array_values(array_filter($rules['gallery'], 'is_string')))->toBe(['nullable', 'array', 'max:3'])
        ->and(end($rules['gallery']))->toBeInstanceOf(ResourceRecordsExist::class);
});

it('shows the picker on the view page through a FieldEntry', function (): void {
    $entries = (new TestPickerPostResource)->infolist();
    $picker = collect($entries)->first(fn ($e) => $e->toArray()['name'] === 'cover_id');

    expect($picker)->toBeInstanceOf(FieldEntry::class)
        ->and($picker->toArray()['attributes']['field']['type'])->toBe('resource_picker');
});

it('adds picker items to search rows and narrows them by ids', function (): void {
    ($this->actAs)(['admin.test-picker-photos.view']);
    $a = TestPickerPhoto::create(['name' => 'Sunset']);
    $b = TestPickerPhoto::create(['name' => 'Harbour']);
    TestPickerPhoto::create(['name' => 'Forest']);

    $response = $this->postJson('/api/admin/test-picker-photos/search', [
        'picker' => true,
        'ids' => [$b->id, $a->id],
    ]);

    $response->assertOk();
    $rows = collect($response->json('payload.data'))->keyBy('id');
    expect($rows)->toHaveCount(2)
        ->and($rows[$a->id]['_picker'])->toBe([
            'id' => $a->id,
            'title' => 'Sunset',
            'subtitle' => null,
            'preview' => '/img/'.$a->id.'.png',
        ]);
});

it('leaves search rows alone without the picker flag', function (): void {
    ($this->actAs)(['admin.test-picker-photos.view']);
    TestPickerPhoto::create(['name' => 'Sunset']);

    $row = $this->postJson('/api/admin/test-picker-photos/search')->json('payload.data.0');

    expect($row)->not->toHaveKey('_picker');
});

it('keeps the picker search behind the target view permission', function (): void {
    ($this->actAs)(['admin.test-picker-posts.*']);

    $this->postJson('/api/admin/test-picker-photos/search', ['picker' => true])->assertStatus(403);
});

it('saves the picked keys, in order for a multiple picker', function (): void {
    ($this->actAs)(['*']);
    $a = TestPickerPhoto::create(['name' => 'A']);
    $b = TestPickerPhoto::create(['name' => 'B']);

    $this->postJson('/api/admin/test-picker-posts/create', [
        'title' => 'Post',
        'cover_id' => $a->id,
        'gallery' => [$b->id, $a->id],
    ])->assertSuccessful();

    $post = TestPickerPost::firstOrFail();
    expect($post->cover_id)->toBe($a->id)
        ->and($post->gallery)->toBe([$b->id, $a->id]);
});

it('rejects keys the target resource does not list', function (): void {
    ($this->actAs)(['*']);
    $hidden = TestPickerPhoto::create(['name' => 'Hidden', 'private' => true]);
    $shown = TestPickerPhoto::create(['name' => 'Shown']);

    $cases = [
        ['cover_id', 999],
        ['cover_id', $hidden->id],
        ['cover_id', [$shown->id]],
        ['gallery', ['a' => $shown->id]],
        ['gallery', [$shown->id, $hidden->id]],
        ['gallery', [1, 2, 3, 4]],
    ];
    foreach ($cases as [$name, $value]) {
        $response = $this->postJson('/api/admin/test-picker-posts/create', [$name => $value]);
        $response->assertStatus(422);
        expect($response->json('payload.messages'))->toHaveKey($name);
    }

    expect(TestPickerPost::count())->toBe(0);
});
