<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Notifications\AdminNotification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    if (! Schema::hasTable('notifications')) {
        Schema::create('notifications', function (Blueprint $t): void {
            $t->uuid('id')->primary();
            $t->string('type');
            $t->morphs('notifiable');
            $t->text('data');
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
        });
    }
});

it('AdminNotification accepts known levels', function (): void {
    foreach (AdminNotification::LEVELS as $level) {
        $n = new AdminNotification('T', 'B', $level);
        expect($n->level)->toBe($level);
    }
});

it('AdminNotification rejects unknown level', function (): void {
    expect(fn () => new AdminNotification('T', 'B', 'critical'))
        ->toThrow(InvalidArgumentException::class);
});

it('AdminNotification::toArray serializes all fields', function (): void {
    $n = new AdminNotification(
        title: 'Импорт завершён',
        body: '1234 записей',
        level: 'success',
        url: '/admin/resources/products',
        icon: 'check',
    );

    expect($n->toArray(null))->toBe([
        'title' => 'Импорт завершён',
        'body' => '1234 записей',
        'level' => 'success',
        'url' => '/admin/resources/products',
        'icon' => 'check',
    ]);
});

it('AdminNotification::via returns database channel by default', function (): void {
    $n = new AdminNotification('T');
    expect($n->via(null))->toBe(['database']);
});

it('admin->notify(AdminNotification) stores in DatabaseNotification', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);

    $admin->notify(new AdminNotification('Hi', 'Hello', 'info', '/admin'));

    $row = $admin->notifications()->first();
    expect($row)->not->toBeNull();
    expect($row->data['title'])->toBe('Hi');
    expect($row->data['level'])->toBe('info');
    expect($row->data['url'])->toBe('/admin');
});

it('SystemController.me returns unread_notifications_count', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $admin->notify(new AdminNotification('A'));
    $admin->notify(new AdminNotification('B'));

    $response = $this->getJson('/api/admin/system/me');
    $response->assertOk();
    expect($response->json('payload.unread_notifications_count'))->toBe(2);
});

it('me unread_count drops to 0 when all notifications are read', function (): void {
    $admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($admin, 'admin');

    $admin->notify(new AdminNotification('A'));
    $admin->unreadNotifications->markAsRead();

    $response = $this->getJson('/api/admin/system/me');
    expect($response->json('payload.unread_notifications_count'))->toBe(0);
});
