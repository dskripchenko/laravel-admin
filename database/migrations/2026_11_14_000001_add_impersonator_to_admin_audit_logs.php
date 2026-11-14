<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who really acted during an impersonation. The actor of a change made while
 * an administrator is signed in as someone else is the impersonated user —
 * these columns keep the administrator beside them, and mark the
 * impersonation's own start and end entries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->string('impersonator_type')->nullable()->after('actor_id')
                ->comment('Класс (morph) администратора, вошедшего под пользователем; null — действие без подмены');
            $table->unsignedBigInteger('impersonator_id')->nullable()->after('impersonator_type')
                ->comment('Идентификатор администратора, вошедшего под пользователем');
            $table->index(['impersonator_type', 'impersonator_id'], 'admin_audit_logs_impersonator_index');
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->dropIndex('admin_audit_logs_impersonator_index');
            $table->dropColumn(['impersonator_type', 'impersonator_id']);
        });
    }
};
