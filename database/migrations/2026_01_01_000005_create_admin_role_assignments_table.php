<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Polymorphic pivot между ролями и любой моделью админа.
 *
 * Позволяет назначать роли как нашему AdminUser (dedicated-режим), так и
 * host-овской User-модели (shared-режим) без жёсткой завязки на конкретный
 * FQCN.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_role_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('admin_roles')->cascadeOnDelete();
            $table->morphs('assignable');
            $table->timestamps();

            $table->unique(['role_id', 'assignable_type', 'assignable_id'], 'admin_role_assignments_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_role_assignments');
    }
};
