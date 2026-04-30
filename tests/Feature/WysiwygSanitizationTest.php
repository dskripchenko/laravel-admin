<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestArticleResource::class);
    AdminApi::clearCache();

    Schema::create('test_articles', function (Blueprint $t): void {
        $t->id();
        $t->string('title');
        $t->text('body')->nullable();
        $t->timestamps();
    });

    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $role = Role::create(['name' => 'S', 'slug' => 's-'.uniqid(), 'permissions' => ['*']]);
    $admin->assignRole($role);
    $this->actingAs($admin->refresh(), 'admin');
});

it('Wysiwyg field on create sanitizes HTML payload', function (): void {
    $response = $this->postJson('/api/admin/test-articles/create', [
        'title' => 'Test',
        'body' => '<p>Hello</p><script>alert("xss")</script><p>World</p>',
    ]);

    $response->assertStatus(201);
    $stored = TestArticle::first();
    expect($stored->body)->not->toContain('script');
    expect($stored->body)->not->toContain('alert');
    expect($stored->body)->toContain('Hello');
    expect($stored->body)->toContain('World');
});

it('Wysiwyg field on update sanitizes HTML payload', function (): void {
    $article = TestArticle::create(['title' => 'X', 'body' => '<p>Old</p>']);

    $this->postJson('/api/admin/test-articles/update', [
        'id' => $article->id,
        'title' => 'X2',
        'body' => '<p>New</p><iframe src="https://evil.com"></iframe>',
    ])->assertOk();

    $stored = $article->fresh();
    expect($stored->body)->not->toContain('iframe');
    expect($stored->body)->toContain('New');
});

it('Wysiwyg::sanitize(false) skips sanitization (advanced trusted use)', function (): void {
    /** @var ResourceRegistry $rr */
    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(TestTrustedArticleResource::class);
    AdminApi::clearCache();

    $this->postJson('/api/admin/test-trusted-articles/create', [
        'title' => 'X',
        'body' => '<p>Trusted</p><iframe src="https://example.com"></iframe>',
    ])->assertStatus(201);

    $stored = TestArticle::first();
    expect($stored->body)->toContain('iframe'); // не санитизировано
});
