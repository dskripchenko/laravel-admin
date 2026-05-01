<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_health_results', function (Blueprint $table): void {
            $table->id();
            $table->string('check_id')->index();
            $table->string('status'); // ok | warning | failing
            $table->text('message')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->timestamp('ran_at')->index();

            $table->index(['check_id', 'ran_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_health_results');
    }
};
