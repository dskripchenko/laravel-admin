<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard's per-user period — the "last N days" filter — persisted
 * alongside the layout so that the choice survives a reload.
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
