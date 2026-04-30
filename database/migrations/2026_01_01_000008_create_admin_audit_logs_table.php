<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table): void {
            $table->id();

            // Кто сделал событие (обычно AdminUser; null для system events).
            $table->nullableMorphs('actor');

            // Над чем (Eloquent model record). null если auth-event.
            $table->nullableMorphs('subject');

            // Тип события: created/updated/deleted/restored/force-deleted/
            // login/logout/password.reset/two-factor.enabled/impersonation.start...
            $table->string('event')->index();

            // Старые/новые значения (для model events) или payload (для auth/custom).
            $table->json('changes')->nullable();

            // Контекст: IP/user-agent/route — debug.
            $table->string('ip', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->string('url')->nullable();

            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'created_at'], 'admin_audit_logs_subject_index');
            $table->index(['actor_type', 'actor_id', 'created_at'], 'admin_audit_logs_actor_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};
