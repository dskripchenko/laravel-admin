<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    Schema::create('logged_posts', function (Blueprint $t): void {
        $t->id();
        $t->string('title')->nullable();
        $t->text('body')->nullable();
        $t->string('secret')->nullable();
        $t->timestamps();
    });

    $this->admin = AdminUser::create([
        'name' => 'Auditor',
        'email' => 'au-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    // AdminUser сам Loggable — чистим логи от события создания admin'а
    // чтобы тесты ассертили только нужные события.
    AuditLog::query()->delete();
});

it('Loggable trait writes created event with after snapshot', function (): void {
    $this->actingAs($this->admin, 'admin');

    $post = TestLoggablePost::create(['title' => 'Hello', 'body' => 'World']);

    $log = AuditLog::query()->where('event', 'created')->first();
    expect($log)->not->toBeNull();
    expect($log->subject_id)->toBe($post->id);
    expect($log->changes['after']['title'])->toBe('Hello');
    expect((int) $log->actor_id)->toBe((int) $this->admin->id);
});

it('Loggable updates writes only changed attributes', function (): void {
    $this->actingAs($this->admin, 'admin');
    $post = TestLoggablePost::create(['title' => 'A', 'body' => 'B']);

    AuditLog::query()->delete(); // оставим только последующее событие
    $post->update(['title' => 'A2']);

    $log = AuditLog::query()->where('event', 'updated')->first();
    expect($log)->not->toBeNull();
    expect($log->changes['before'])->toBe(['title' => 'A']);
    expect($log->changes['after'])->toBe(['title' => 'A2']);
    expect($log->changes['after'])->not->toHaveKey('body');
});

it('Loggable deleted writes before snapshot', function (): void {
    $this->actingAs($this->admin, 'admin');
    $post = TestLoggablePost::create(['title' => 'Delete me']);

    AuditLog::query()->delete();
    $post->delete();

    $log = AuditLog::query()->where('event', 'deleted')->first();
    expect($log)->not->toBeNull();
    expect($log->changes['before']['title'])->toBe('Delete me');
});

it('Loggable filters out excluded attributes (config + per-model)', function (): void {
    $this->actingAs($this->admin, 'admin');
    config()->set('admin.audit.excluded_attributes', ['password']);

    TestLoggablePost::create([
        'title' => 'X',
        'secret' => 'super-secret',
    ]);

    $log = AuditLog::query()->where('event', 'created')->first();
    expect($log->changes['after'])->not->toHaveKey('secret');
});

it('Loggable does not write when audit.enabled=false', function (): void {
    config()->set('admin.audit.enabled', false);
    TestLoggablePost::create(['title' => 'Silent']);

    expect(AuditLog::count())->toBe(0);
});

it('Loggable records null actor when no one is logged in', function (): void {
    TestLoggablePost::create(['title' => 'System']);

    $log = AuditLog::query()->first();
    expect($log->actor_id)->toBeNull();
    expect($log->actor_type)->toBeNull();
});

it('AuditLog::scopeForSubject filters by morph', function (): void {
    $this->actingAs($this->admin, 'admin');
    $a = TestLoggablePost::create(['title' => 'A']);
    $b = TestLoggablePost::create(['title' => 'B']);

    $logs = AuditLog::query()->forSubject($a)->get();
    expect($logs)->toHaveCount(1);
    expect($logs->first()->subject_id)->toBe($a->id);
});

it('Auth Login event is recorded for admin guard', function (): void {
    config()->set('admin.audit.enabled', true);
    config()->set('admin.audit.log_auth_events', true);
    AuditLog::query()->delete();

    $this->actingAs($this->admin, 'admin');
    Illuminate\Support\Facades\Event::dispatch(
        new Illuminate\Auth\Events\Login('admin', $this->admin, false),
    );

    $log = AuditLog::query()->where('event', 'login')->first();
    expect($log)->not->toBeNull();
    expect((int) $log->actor_id)->toBe((int) $this->admin->id);
    expect($log->changes['payload']['guard'])->toBe('admin');
});

it('Auth Failed event is recorded with email payload', function (): void {
    Illuminate\Support\Facades\Event::dispatch(
        new Illuminate\Auth\Events\Failed('admin', null, ['email' => 'wrong@example.com']),
    );

    $log = AuditLog::query()->where('event', 'login.failed')->first();
    expect($log)->not->toBeNull();
    expect($log->changes['payload']['email'])->toBe('wrong@example.com');
});

it('Auth events for non-admin guard are NOT recorded', function (): void {
    Illuminate\Support\Facades\Event::dispatch(
        new Illuminate\Auth\Events\Login('web', $this->admin, false),
    );

    expect(AuditLog::query()->where('event', 'login')->count())->toBe(0);
});

it('login auth event respects log_auth_events=false', function (): void {
    config()->set('admin.audit.log_auth_events', false);

    Illuminate\Support\Facades\Event::dispatch(
        new Illuminate\Auth\Events\Login('admin', $this->admin, false),
    );

    expect(AuditLog::count())->toBe(0);
});

it('records exactly one login and one logout row per real auth cycle', function (): void {
    config()->set('admin.audit.enabled', true);
    config()->set('admin.audit.log_auth_events', true);
    AuditLog::query()->delete();

    // SessionGuard сам диспатчит Login/Logout — контроллер не должен
    // дублировать события (дубли давали по две audit-строки на вход/выход).
    $this->postJson('/api/admin/auth/login', [
        'email' => $this->admin->email,
        'password' => 'secret',
    ])->assertOk();

    expect(AuditLog::query()->where('event', 'login')->count())->toBe(1);

    $this->postJson('/api/admin/auth/logout')->assertOk();

    expect(AuditLog::query()->where('event', 'logout')->count())->toBe(1);
});

it('resolveTypeLabel: config map wins, else class_basename, else null (BL-4)', function (): void {
    config()->set('admin.audit.type_labels', [
        AdminUser::class => 'Администратор',
    ]);

    // config map
    expect(AuditLog::resolveTypeLabel(AdminUser::class))->toBe('Администратор');
    // fallback → class_basename для незамапленного класса
    expect(AuditLog::resolveTypeLabel('App\\Models\\TemplateVariable'))->toBe('TemplateVariable');
    // null/пусто → null (login без subject)
    expect(AuditLog::resolveTypeLabel(null))->toBeNull();
    expect(AuditLog::resolveTypeLabel(''))->toBeNull();
});

it('exposes actor_label / subject_label accessors in serialization (BL-4)', function (): void {
    config()->set('admin.audit.type_labels', [
        AdminUser::class => 'Администратор',
    ]);

    $log = AuditLog::create([
        'actor_type' => AdminUser::class,
        'actor_id' => 1,
        'subject_type' => AdminUser::class,
        'subject_id' => 1,
        'event' => 'updated',
    ]);

    $arr = $log->fresh()->toArray();
    expect($arr['actor_label'])->toBe('Администратор');
    expect($arr['subject_label'])->toBe('Администратор');
});

it('AdminNotification is queued (не блокирует request path)', function (): void {
    expect(new Dskripchenko\LaravelAdmin\Notifications\AdminNotification('t'))
        ->toBeInstanceOf(Illuminate\Contracts\Queue\ShouldQueue::class);
});
