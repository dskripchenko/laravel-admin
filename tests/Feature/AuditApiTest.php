<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Audit\AuditTimelineProjector;
use Dskripchenko\LaravelAdmin\Layout\AuditTrail;
use Dskripchenko\LaravelAdmin\Models\AdminUser;

beforeEach(function (): void {
    $this->admin = AdminUser::create([
        'name' => 'Admin',
        'email' => 'a-'.uniqid().'@example.com',
        'password' => 'secret',
    ]);
    AuditLog::query()->delete();
    $this->actingAs($this->admin, 'admin');
});

it('audit.list returns paginated logs ordered desc', function (): void {
    AuditLog::create([
        'event' => 'created',
        'subject_type' => 'User',
        'subject_id' => 1,
        'actor_type' => $this->admin->getMorphClass(),
        'actor_id' => $this->admin->id,
        'changes' => ['after' => ['name' => 'A']],
    ]);
    AuditLog::create([
        'event' => 'updated',
        'subject_type' => 'User',
        'subject_id' => 1,
        'actor_type' => $this->admin->getMorphClass(),
        'actor_id' => $this->admin->id,
        'changes' => ['before' => ['name' => 'A'], 'after' => ['name' => 'B']],
    ]);

    $response = $this->getJson('/api/admin/audit/list');
    $response->assertOk();
    $data = $response->json('payload.data');
    expect($data)->toHaveCount(2);
    expect($data[0]['event'])->toBe('updated'); // desc
    expect($data[0]['summary'])->toBe('Изменено');
    expect($data[1]['event'])->toBe('created');
});

it('audit.list filters by subject_type/subject_id', function (): void {
    AuditLog::create([
        'event' => 'created',
        'subject_type' => 'A',
        'subject_id' => 1,
    ]);
    AuditLog::create([
        'event' => 'created',
        'subject_type' => 'B',
        'subject_id' => 1,
    ]);

    $response = $this->getJson('/api/admin/audit/list?subject_type=A');
    expect($response->json('payload.data'))->toHaveCount(1);
});

it('audit.list filters by date range', function (): void {
    $old = AuditLog::create(['event' => 'old']);
    $old->forceFill(['created_at' => '2020-01-01 00:00:00'])->save();

    $new = AuditLog::create(['event' => 'new']);
    $new->forceFill(['created_at' => '2025-06-01 00:00:00'])->save();

    $response = $this->getJson('/api/admin/audit/list?from=2024-01-01');
    $events = collect($response->json('payload.data'))->pluck('event')->all();
    expect($events)->toBe(['new']);
});

it('audit.timeline requires subject_type + subject_id', function (): void {
    $this->getJson('/api/admin/audit/timeline')->assertStatus(422);
});

it('audit.timeline returns logs for specific subject only', function (): void {
    AuditLog::create([
        'event' => 'created',
        'subject_type' => 'User',
        'subject_id' => 1,
    ]);
    AuditLog::create([
        'event' => 'created',
        'subject_type' => 'User',
        'subject_id' => 2,
    ]);

    $response = $this->getJson('/api/admin/audit/timeline?subject_type=User&subject_id=1');
    $response->assertOk();
    expect($response->json('payload.data'))->toHaveCount(1);
});

it('AuditTimelineProjector formats events with summary and diff', function (): void {
    $log = AuditLog::create([
        'event' => 'updated',
        'subject_type' => 'User',
        'subject_id' => 1,
        'actor_type' => $this->admin->getMorphClass(),
        'actor_id' => $this->admin->id,
        'changes' => [
            'before' => ['name' => 'Old', 'email' => 'old@example.com'],
            'after' => ['name' => 'New', 'email' => 'new@example.com'],
        ],
    ]);

    $projected = AuditTimelineProjector::project(AuditLog::query()->whereKey($log->id)->get());
    $card = $projected[0];

    expect($card['event'])->toBe('updated');
    expect($card['summary'])->toBe('Изменено');
    expect($card['diff'])->toHaveCount(2);
    expect($card['actor']['name'])->toBe($this->admin->name);
});

it('AuditTimelineProjector handles auth events with payload diff=null', function (): void {
    AuditLog::create([
        'event' => 'login',
        'changes' => ['payload' => ['guard' => 'admin']],
    ]);
    $card = AuditTimelineProjector::project(AuditLog::all())[0];
    expect($card['summary'])->toBe('Вход в систему');
    expect($card['diff'])->toBeNull();
});

it('AuditTrail layout serializes with subjectType and limit', function (): void {
    $arr = AuditTrail::for(App\Models\User::class)
        ->fromState('id')
        ->limit(50)
        ->withPermission('admin.users.view')
        ->toArray();

    expect($arr['type'])->toBe('audit_trail');
    expect($arr['props']['subjectType'])->toBe(App\Models\User::class);
    expect($arr['props']['idStateKey'])->toBe('id');
    expect($arr['props']['limit'])->toBe(50);
    expect($arr['props']['permission'])->toBe('admin.users.view');
});
