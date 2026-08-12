<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->id();

            // The group: a resource's key, or a namespace.
            $table->string('group')->index();

            // The key inside that group — the field's name.
            $table->string('key');

            // The value as JSON, so any type fits: string, int, array, bool, null.
            $table->json('value')->nullable();

            // An optional owner, for per-user or per-tenant settings.
            $table->nullableMorphs('owner');

            $table->timestamps();

            $table->unique(['group', 'key', 'owner_type', 'owner_id'], 'admin_settings_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_settings');
    }
};
