<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * A simple notification for the tests.
 */
final class TestSimpleNotification extends Notification
{
    public function __construct(public string $title, public string $body) {}

    public function via(mixed $notifiable): array
    {
        return ['database'];
    }

    public function toArray(mixed $notifiable): array
    {
        return [
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}

beforeEach(function (): void {
    if (! Schema::hasTable('notifications')) {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    $this->admin = AdminUser::create([
        'name' => 'A',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    $this->actingAs($this->admin, 'admin');
});

function createNotificationFor(AdminUser $user, string $title, string $body, bool $read = false): DatabaseNotification
{
    return DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => TestSimpleNotification::class,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'data' => ['title' => $title, 'body' => $body],
        'read_at' => $read ? now() : null,
    ]);
}

it('notifications.list returns paginated user notifications', function (): void {
    createNotificationFor($this->admin, 'A', 'body A');
    createNotificationFor($this->admin, 'B', 'body B');

    $response = $this->getJson('/api/admin/notifications/list');
    $response->assertOk();
    expect($response->json('payload.data'))->toHaveCount(2);
    expect($response->json('payload.meta.unread_count'))->toBe(2);
});

it('notifications.list filter type=unread', function (): void {
    createNotificationFor($this->admin, 'unread', 'x', read: false);
    createNotificationFor($this->admin, 'read', 'y', read: true);

    $response = $this->getJson('/api/admin/notifications/list?type=unread');
    expect($response->json('payload.data'))->toHaveCount(1);
});

it('notifications.list filter type=read', function (): void {
    createNotificationFor($this->admin, 'unread', 'x', read: false);
    createNotificationFor($this->admin, 'read', 'y', read: true);

    $response = $this->getJson('/api/admin/notifications/list?type=read');
    expect($response->json('payload.data'))->toHaveCount(1);
});

it('notifications.unread returns count + last 20', function (): void {
    for ($i = 0; $i < 25; $i++) {
        createNotificationFor($this->admin, "n$i", "b$i");
    }

    $response = $this->getJson('/api/admin/notifications/unread');
    $response->assertOk();
    expect($response->json('payload.count'))->toBe(25);
    expect($response->json('payload.data'))->toHaveCount(20);
});

it('notifications.markAsRead marks single', function (): void {
    $n = createNotificationFor($this->admin, 'X', 'Y');

    $response = $this->postJson('/api/admin/notifications/markAsRead', ['id' => $n->id]);
    $response->assertOk();
    expect($response->json('payload.unread_count'))->toBe(0);
    expect($n->fresh()->read_at)->not->toBeNull();
});

it('notifications.markAsRead returns 404 for unknown id', function (): void {
    $response = $this->postJson('/api/admin/notifications/markAsRead', ['id' => 'fake-uuid']);
    $response->assertStatus(404);
});

it('notifications.markAsRead does not see other users notifications', function (): void {
    $other = AdminUser::create([
        'name' => 'O', 'email' => 'o-'.uniqid().'@example.com', 'password' => 'p',
    ]);
    $foreign = createNotificationFor($other, 'F', 'B');

    $response = $this->postJson('/api/admin/notifications/markAsRead', ['id' => $foreign->id]);
    $response->assertStatus(404);
    expect($foreign->fresh()->read_at)->toBeNull();
});

it('notifications.markAllAsRead bulk-marks unread', function (): void {
    createNotificationFor($this->admin, 'A', 'a');
    createNotificationFor($this->admin, 'B', 'b');
    createNotificationFor($this->admin, 'C', 'c', read: true);

    $response = $this->postJson('/api/admin/notifications/markAllAsRead');
    $response->assertOk();
    expect($response->json('payload.updated'))->toBe(2);
    expect($response->json('payload.unread_count'))->toBe(0);
});

it('notifications.destroy removes notification', function (): void {
    $n = createNotificationFor($this->admin, 'X', 'Y');

    $this->postJson('/api/admin/notifications/destroy', ['id' => $n->id])->assertOk();
    expect(DatabaseNotification::find($n->id))->toBeNull();
});

it('notifications endpoints require authentication', function (): void {
    $this->app['auth']->guard('admin')->logout();
    $this->getJson('/api/admin/notifications/list')->assertStatus(401);
    $this->getJson('/api/admin/notifications/unread')->assertStatus(401);
});

it('AdminUser->notify integrates with Laravel notification system', function (): void {
    $this->admin->notify(new TestSimpleNotification('Hi', 'Hello there'));

    expect($this->admin->notifications()->count())->toBe(1);
    expect($this->admin->unreadNotifications()->count())->toBe(1);
    $stored = $this->admin->notifications()->first();
    expect($stored->data['title'])->toBe('Hi');
});

it('notifications.list translates the stored text into the reader locale', function (): void {
    Illuminate\Support\Facades\Lang::addLines([
        '*.Импорт завершён' => 'Import finished',
        '*.Импортировано записей: :count' => ':count records imported',
    ], 'en');
    app()->setLocale('en');

    $this->admin->notify(new Dskripchenko\LaravelAdmin\Notifications\AdminNotification(
        title: 'Импорт завершён',
        body: 'Импортировано записей: :count',
        level: 'success',
        params: ['count' => 1234],
    ));
    // A row stored before params existed, with text that has no translation.
    createNotificationFor($this->admin, 'Готовый текст', 'без перевода');

    $data = collect($this->withHeader('X-Admin-Locale', 'en')
        ->getJson('/api/admin/notifications/list')
        ->assertOk()
        ->json('payload.data'))->pluck('data');

    $titles = $data->pluck('title')->all();
    $bodies = $data->pluck('body')->all();
    expect($titles)->toContain('Import finished')->toContain('Готовый текст');
    expect($bodies)->toContain('1234 records imported')->toContain('без перевода');
});

it('notifications.list keeps the source text in the source locale, with params filled', function (): void {
    app()->setLocale('ru');
    $this->admin->notify(new Dskripchenko\LaravelAdmin\Notifications\AdminNotification(
        title: 'Счёт :number оплачен',
        params: ['number' => 'A-17'],
    ));

    $data = $this->withHeader('X-Admin-Locale', 'ru')
        ->getJson('/api/admin/notifications/list')
        ->json('payload.data.0.data');

    expect($data['title'])->toBe('Счёт A-17 оплачен');
    expect($data['params'])->toBe(['number' => 'A-17']);
});

it('notifications.unread translates as well, honouring per-key params', function (): void {
    Illuminate\Support\Facades\Lang::addLines(['*.Новый заказ :id' => 'New order :id'], 'en');
    app()->setLocale('en');
    DatabaseNotification::create([
        'id' => (string) Str::uuid(),
        'type' => 'HostNotification',
        'notifiable_type' => $this->admin->getMorphClass(),
        'notifiable_id' => $this->admin->id,
        'data' => ['subject' => 'Новый заказ :id', 'subject_params' => ['id' => 42], 'params' => ['id' => 1]],
    ]);

    $response = $this->withHeader('X-Admin-Locale', 'en')->getJson('/api/admin/notifications/unread');

    expect(collect($response->json('payload'))->flatten()->all())->toContain('New order 42');
});
