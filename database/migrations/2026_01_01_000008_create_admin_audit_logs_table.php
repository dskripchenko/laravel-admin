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

            // Who caused the event, usually an AdminUser; null for the system's own.
            $table->nullableMorphs('actor');

            // What it happened to — an Eloquent record; null for an auth event.
            $table->nullableMorphs('subject');

            // The event's kind: created, updated, deleted, restored,
            // force-deleted, login, logout, password.reset,
            // two-factor.enabled, impersonation.start and so on.
            $table->string('event')->index();

            // The old and new values, for a model event, or the payload of an auth or custom one.
            $table->json('changes')->nullable();

            // The context — IP, user agent, route — for debugging.
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
