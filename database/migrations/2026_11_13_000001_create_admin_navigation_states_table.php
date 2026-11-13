<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A user's own navigation in a panel: the favourites, the recently opened
 * pages and the menu groups they folded or unfolded. One row per user and
 * panel; kept on the server so that it follows the user to another browser.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_navigation_states', function (Blueprint $table): void {
            $table->comment('Навигация пользователя панели: избранное, недавнее, свёрнутые группы меню');

            $table->id();

            // Whose navigation it is — usually an AdminUser.
            $table->string('owner_type')->comment('Класс (morph) владельца — пользователя панели');
            $table->string('owner_id')->comment('Идентификатор владельца');

            $table->string('panel', 64)->default('admin')->comment('Панель (Panels), к которой относится состояние');

            $table->json('favorites')->nullable()->comment('Избранное: упорядоченный список {url, label, icon}');
            $table->json('recent')->nullable()->comment('Недавно открытые экраны и записи: {url, path, label, subtitle, kind, at}, новые первыми');
            $table->json('menu_groups')->nullable()->comment('Свёрнутость групп меню: {ключ пункта: раскрыт ли}');

            $table->timestamps();

            $table->unique(['owner_type', 'owner_id', 'panel'], 'admin_navigation_states_owner_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_navigation_states');
    }
};
