<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The table of the administrators' password-reset tokens — a broker separate from the host project's.
 *
 * Registered in the config by AdminGuardRegistrar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_password_resets', function (Blueprint $blueprint): void {
            $blueprint->string('email')->primary();
            $blueprint->string('token');
            $blueprint->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_password_resets');
    }
};
