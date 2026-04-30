<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_import_processes', function (Blueprint $table): void {
            $table->id();

            // Resource slug куда импортируем.
            $table->string('resource_slug')->index();

            // Кто запустил.
            $table->nullableMorphs('owner');

            // Путь к загруженному файлу (storage disk default из config).
            $table->string('source_path');

            // CSV column → Field name mapping.
            $table->json('mapping');

            // Статус: pending/running/completed/failed.
            $table->string('status')->default('pending')->index();

            // Прогресс.
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            // Ошибки построчно: [{row: int, error: string}].
            $table->json('errors')->nullable();

            // Связанный delayed_process uuid.
            $table->string('process_uuid')->nullable()->index();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_import_processes');
    }
};
