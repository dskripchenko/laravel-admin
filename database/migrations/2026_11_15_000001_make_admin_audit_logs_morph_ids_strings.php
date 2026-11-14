<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log writes entries about any model of the host, and about any
 * user who acts — but its morph ids were created by nullableMorphs() as
 * bigint. A record with a uuid, ulid or other string key (a reveal of its
 * masked column, a Loggable change, an impersonation of such a user) could
 * not be written: PostgreSQL refused the value outright, MySQL cut it to a
 * number. The ids become strings wide enough for any of those keys; integer
 * keys are kept as their decimal text, the indexes keep their columns.
 *
 * Reversible while every stored id is still an integer: down() refuses to run
 * once an entry with a non-numeric id exists, since that id cannot be put
 * back into a bigint without losing which record it named.
 */
return new class extends Migration
{
    /** The width of a stored id: a uuid takes 36, a ulid 26. */
    private const ID_LENGTH = 64;

    public function up(): void
    {
        $columns = $this->columns();

        Schema::table('admin_audit_logs', function (Blueprint $table) use ($columns): void {
            foreach ($columns as $column => $comment) {
                $table->string($column, self::ID_LENGTH)->nullable()->comment($comment)->change();
            }
        });
    }

    public function down(): void
    {
        $columns = $this->columns();

        foreach (array_keys($columns) as $column) {
            $values = DB::table('admin_audit_logs')->whereNotNull($column)->distinct()->pluck($column);
            foreach ($values as $value) {
                if (preg_match('/^-?\d{1,18}$/', (string) $value) !== 1) {
                    throw new RuntimeException(
                        "admin_audit_logs.{$column} holds the non-numeric id '{$value}': it cannot go back to bigint without losing data."
                    );
                }
            }
        }

        if (DB::connection($this->getConnection())->getDriverName() === 'pgsql') {
            // PostgreSQL has no implicit cast from text back to bigint.
            $table = DB::connection($this->getConnection())->getTablePrefix().'admin_audit_logs';
            foreach ($columns as $column => $comment) {
                DB::statement("alter table \"{$table}\" alter column \"{$column}\" type bigint using \"{$column}\"::bigint");
            }

            return;
        }

        Schema::table('admin_audit_logs', function (Blueprint $table) use ($columns): void {
            foreach ($columns as $column => $comment) {
                $table->unsignedBigInteger($column)->nullable()->comment($comment)->change();
            }
        });
    }

    /**
     * The morph id columns present in the table, with their comments; the
     * impersonator's exists only after the 2026_11_14 migration.
     *
     * @return array<string, string>
     */
    private function columns(): array
    {
        $columns = [
            'actor_id' => 'Идентификатор того, кто вызвал событие (ключ модели строкой); null — событие системы',
            'subject_id' => 'Идентификатор записи, с которой произошло событие (ключ модели строкой: число, uuid, ulid); null — событие входа',
        ];
        if (Schema::hasColumn('admin_audit_logs', 'impersonator_id')) {
            $columns['impersonator_id'] = 'Идентификатор администратора, вошедшего под пользователем (ключ модели строкой)';
        }

        return $columns;
    }
};
