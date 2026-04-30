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

            // Группа (ключ Resource'а / namespace).
            $table->string('group')->index();

            // Ключ внутри группы (имя поля).
            $table->string('key');

            // Значение в JSON (поддерживает любые типы: string/int/array/bool/null).
            $table->json('value')->nullable();

            // Опциональный owner для per-user/per-tenant settings.
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
