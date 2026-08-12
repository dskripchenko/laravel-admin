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

            // The slug of the resource being imported into.
            $table->string('resource_slug')->index();

            // Who started it.
            $table->nullableMorphs('owner');

            // The path of the uploaded file, on the default disk from the config.
            $table->string('source_path');

            // CSV column → Field name mapping.
            $table->json('mapping');

            // The status: pending, running, completed or failed.
            $table->string('status')->default('pending')->index();

            // The progress.
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);

            // The errors, line by line: [{row: int, error: string}].
            $table->json('errors')->nullable();

            // The uuid of the related delayed process.
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
