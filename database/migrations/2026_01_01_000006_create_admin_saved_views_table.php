<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->string('resource_slug')->index();
            $table->string('name');

            // Кому принадлежит view: null = глобальный (видим всем).
            $table->nullableMorphs('owner');

            // Сериализованное состояние таблицы: filters, sort, columns,
            // per_page, q (поиск).
            $table->json('state');

            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->unique(['resource_slug', 'owner_type', 'owner_id', 'name'], 'admin_saved_views_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_saved_views');
    }
};
