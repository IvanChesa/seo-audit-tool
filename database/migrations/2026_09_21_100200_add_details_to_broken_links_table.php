<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broken_links', function (Blueprint $table) {
            $table->string('url', 2048)->change();
            $table->boolean('is_internal')->default(false)->after('url');
            // Why the link is considered broken when there is no status code
            // (timeout, connection_failed, dns_error, tls_error...).
            $table->string('error', 32)->nullable()->after('status_code');
        });
    }

    public function down(): void
    {
        Schema::table('broken_links', function (Blueprint $table) {
            $table->dropColumn(['is_internal', 'error']);
            $table->string('url')->change();
        });
    }
};
