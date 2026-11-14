<?php

declare(strict_types=1);

use Dskripchenko\LaravelAdmin\Audit\AuditLog;
use Dskripchenko\LaravelAdmin\Audit\Concerns\Loggable;
use Dskripchenko\LaravelAdmin\Field\Input;
use Dskripchenko\LaravelAdmin\Http\AdminApi;
use Dskripchenko\LaravelAdmin\Models\AdminUser;
use Dskripchenko\LaravelAdmin\Permission\Models\Role;
use Dskripchenko\LaravelAdmin\Resource\Resource;
use Dskripchenko\LaravelAdmin\Resource\ResourceRegistry;
use Dskripchenko\LaravelAdmin\Table\TableColumn;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 1.75.2: the audit log names records by a string id, so a model keyed by a
 * uuid is audited like one keyed by an integer — its Loggable changes, the
 * reveal of its masked column, its timeline. Run with ADMIN_TEST_DB_DRIVER
 * on PostgreSQL or MySQL too: SQLite takes any value into a bigint column.
 */

/** @internal */
final class AuditUuidContract extends Model
{
    use HasUuids;
    use Loggable;

    protected $table = 'audit_uuid_contracts';

    protected $guarded = [];
}

/** @internal */
final class AuditIntContract extends Model
{
    use Loggable;

    protected $table = 'audit_int_contracts';

    protected $guarded = [];
}

/** @internal */
final class AuditUuidContractResource extends Resource
{
    public static string $model = AuditUuidContract::class;

    public static function slug(): string
    {
        return 'audit-uuid-contracts';
    }

    public static function permission(): string
    {
        return 'admin.contracts';
    }

    public function fields(): array
    {
        return [Input::make('holder')];
    }

    public function columns(): array
    {
        return [
            TableColumn::make('id'),
            TableColumn::make('holder')->masked('name'),
        ];
    }
}

beforeEach(function (): void {
    Schema::create('audit_uuid_contracts', function (Blueprint $t): void {
        $t->uuid('id')->primary();
        $t->string('holder')->nullable();
        $t->timestamps();
    });
    Schema::create('audit_int_contracts', function (Blueprint $t): void {
        $t->id();
        $t->string('holder')->nullable();
        $t->timestamps();
    });

    $rr = app(ResourceRegistry::class);
    $rr->clear();
    $rr->add(AuditUuidContractResource::class);
    AdminApi::clearCache();

    $this->admin = AdminUser::create(['name' => 'A', 'email' => 'a-'.uniqid().'@example.com', 'password' => 'secret']);
    $role = Role::create(['name' => 'C', 'slug' => 'c-'.uniqid(), 'permissions' => ['admin.contracts.view', 'admin.contracts.reveal', 'admin.systems.audit.view']]);
    $this->admin->assignRole($role);
    $this->actingAs($this->admin->refresh(), 'admin');
    AuditLog::query()->delete();
});

afterEach(function (): void {
    Schema::dropIfExists('audit_uuid_contracts');
    Schema::dropIfExists('audit_int_contracts');
});

it('audits the changes of a uuid-keyed Loggable model', function (): void {
    $contract = AuditUuidContract::create(['holder' => 'Ann Lee']);
    $contract->update(['holder' => 'Ann Smith']);

    $logs = AuditLog::query()->forSubject($contract)->orderBy('id')->get();
    expect($logs->pluck('event')->all())->toBe(['created', 'updated'])
        ->and($logs[0]->subject_id)->toBe($contract->getKey())
        ->and($logs[0]->actor_id)->toBe($this->admin->getKey())
        ->and($contract->auditLogs()->count())->toBe(2)
        ->and(AuditUuidContract::query()->with('auditLogs')->first()->auditLogs)->toHaveCount(2);
});

it('stores an integer key as text, reads it back as an int and finds it by the model', function (): void {
    $contract = AuditIntContract::create(['holder' => 'Bob']);
    AuditIntContract::create(['holder' => 'Cid']);

    $log = AuditLog::query()->forSubject($contract)->sole();
    expect(DB::table('admin_audit_logs')->where('id', $log->id)->value('subject_id'))->toBe((string) $contract->getKey())
        ->and($log->subject_id)->toBe($contract->getKey())
        ->and($log->subject)->toBeInstanceOf(AuditIntContract::class)
        ->and($log->subject->is($contract))->toBeTrue()
        ->and($contract->auditLogs()->count())->toBe(1)
        ->and(AuditIntContract::query()->with('auditLogs')->get()->map(fn ($c) => $c->auditLogs->count())->all())->toBe([1, 1])
        ->and(AuditLog::query()->with('subject')->get()->every(fn ($l) => $l->subject !== null))->toBeTrue();
});

it('reveals a masked column of a uuid-keyed record and audits the reveal', function (): void {
    $contract = AuditUuidContract::create(['holder' => 'Ann Lee']);
    AuditLog::query()->delete();

    $value = $this->postJson('/api/admin/audit-uuid-contracts/reveal', ['id' => $contract->getKey(), 'column' => 'holder'])
        ->assertOk()
        ->json('payload.value');
    expect($value)->toBe('Ann Lee');

    $log = AuditLog::query()->where('event', 'pii.revealed')->sole();
    expect($log->subject_id)->toBe($contract->getKey())
        ->and($log->subject_type)->toBe($contract->getMorphClass())
        ->and($log->subject->is($contract))->toBeTrue();
});

it('shows the timeline of a uuid-keyed record', function (): void {
    $contract = AuditUuidContract::create(['holder' => 'Ann Lee']);

    $data = $this->getJson('/api/admin/audit/timeline?'.http_build_query([
        'subject_type' => $contract->getMorphClass(),
        'subject_id' => $contract->getKey(),
    ]))->assertOk()->json('payload.data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['subject']['id'])->toBe($contract->getKey())
        ->and($data[0]['actor']['id'])->toBe($this->admin->getKey());
});

it('migrates the morph ids back to bigint and forward again while they are numeric', function (): void {
    AuditIntContract::create(['holder' => 'Bob']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_15_000001_make_admin_audit_logs_morph_ids_strings.php';

    $migration->down();
    expect(Schema::getColumnType('admin_audit_logs', 'subject_id'))->toBeIn(['integer', 'bigint', 'int8']);
    $raw = DB::table('admin_audit_logs')->value('subject_id');
    expect((string) $raw)->toBe('1');

    $migration->up();
    expect(Schema::getColumnType('admin_audit_logs', 'subject_id'))->toBeIn(['varchar', 'character varying', 'text', 'string'])
        ->and((string) DB::table('admin_audit_logs')->value('subject_id'))->toBe('1');
    $indexes = collect(Schema::getIndexes('admin_audit_logs'))->pluck('columns', 'name');
    expect($indexes['admin_audit_logs_subject_index'])->toBe(['subject_type', 'subject_id', 'created_at'])
        ->and($indexes['admin_audit_logs_actor_index'])->toBe(['actor_type', 'actor_id', 'created_at'])
        ->and($indexes['admin_audit_logs_impersonator_index'])->toBe(['impersonator_type', 'impersonator_id']);
});

it('refuses to migrate back once a non-numeric id is stored', function (): void {
    AuditUuidContract::create(['holder' => 'Ann']);
    $migration = require dirname(__DIR__, 2).'/database/migrations/2026_11_15_000001_make_admin_audit_logs_morph_ids_strings.php';

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'non-numeric id');
});
