<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The polymorphic pivot between the roles and any administrator model.
 *
 * It lets roles be assigned both to our AdminUser, in the dedicated mode, and
 * to the host's own User model, in the shared one, without tying anything to a
 * particular FQCN.
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
