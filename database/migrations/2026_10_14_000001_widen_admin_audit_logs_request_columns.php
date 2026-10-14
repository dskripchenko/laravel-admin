<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit log keeps the request's URL and User-Agent cut to
 * `admin.audit.url_max_length` (2048) and `user_agent_max_length` (1024), but
 * the columns were varchar(255): on PostgreSQL and MySQL any longer value —
 * an OAuth callback with its code, a long browser string — made the write
 * fail, and with it the request that was being audited. SQLite does not
 * check lengths, which is how it went unnoticed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->text('user_agent')->nullable()->change();
            $table->text('url')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table): void {
            $table->string('user_agent')->nullable()->change();
            $table->string('url')->nullable()->change();
        });
    }
};
