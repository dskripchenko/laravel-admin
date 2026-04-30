<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboard_layouts', function (Blueprint $table): void {
            $table->id();

            // ID dashboard'а — соответствует Layout/Dashboard::key().
            $table->string('dashboard_key')->index();

            // Owner — обычно AdminUser; nullable для shared/global layouts.
            $table->nullableMorphs('owner');

            // Сериализованный массив виджетов в порядке отображения:
            //   [{slug, size, position, hidden?}, ...]
            $table->json('widgets');

            $table->timestamps();

            $table->unique(
                ['dashboard_key', 'owner_type', 'owner_id'],
                'admin_dashboard_layouts_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboard_layouts');
    }
};
