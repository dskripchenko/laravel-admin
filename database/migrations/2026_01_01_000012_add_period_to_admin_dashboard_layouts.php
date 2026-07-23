<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-user период дашборда (фильтр «за N дней») — персистится вместе с
 * layout'ом, чтобы выбор не сбрасывался при перезагрузке (BL-16).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('admin_dashboard_layouts')) {
            return;
        }
        if (Schema::hasColumn('admin_dashboard_layouts', 'period')) {
            return;
        }
        Schema::table('admin_dashboard_layouts', function (Blueprint $table): void {
            $table->string('period')->nullable()->after('widgets');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('admin_dashboard_layouts', 'period')) {
            Schema::table('admin_dashboard_layouts', function (Blueprint $table): void {
                $table->dropColumn('period');
            });
        }
    }
};
