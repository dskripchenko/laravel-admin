<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_pulse_samples', function (Blueprint $table): void {
            $table->id();
            $table->string('kind', 32)->index();          // 'request' | 'query' | 'job' | 'exception' | 'cache'
            $table->string('key', 255)->index();           // route либо sql_fingerprint либо exception class
            $table->string('label', 255)->nullable();      // optional human label
            $table->unsignedInteger('duration_ms')->default(0);
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('sampled_at')->index();

            $table->index(['kind', 'sampled_at']);
            $table->index(['kind', 'key', 'sampled_at']);
        });

        Schema::create('admin_pulse_aggregates', function (Blueprint $table): void {
            $table->id();
            $table->string('bucket', 32);                  // 'route.p95' | 'top_slow_query' | etc.
            $table->string('key', 255);
            $table->json('metrics');                        // {p50, p95, p99, count, ...}
            $table->timestamp('period_start')->index();
            $table->timestamp('period_end')->index();
            $table->timestamp('aggregated_at');

            $table->index(['bucket', 'period_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_pulse_aggregates');
        Schema::dropIfExists('admin_pulse_samples');
    }
};
