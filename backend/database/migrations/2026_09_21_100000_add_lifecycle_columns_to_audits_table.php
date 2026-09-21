<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->string('url', 2048)->change();
            $table->string('host')->nullable()->after('url');
            $table->string('final_url', 2048)->nullable()->after('host');
            $table->unsignedSmallInteger('http_status')->nullable()->after('score');
            $table->string('error_code', 64)->nullable()->after('http_status');
            $table->string('error_message')->nullable()->after('error_code');
            // NULL marks audits created before the structured report existed.
            $table->unsignedTinyInteger('report_version')->nullable()->after('error_message');
            $table->timestamp('started_at')->nullable()->after('report_version');
            $table->timestamp('finished_at')->nullable()->after('started_at');

            // History list: filter by status, newest first.
            $table->index(['status', 'id']);
            $table->index('host');
        });

        DB::table('audits')->whereNull('host')->orderBy('id')->each(function (object $audit): void {
            DB::table('audits')->where('id', $audit->id)->update([
                'host' => parse_url($audit->url, PHP_URL_HOST) ?: null,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropIndex(['status', 'id']);
            $table->dropIndex(['host']);
            $table->dropColumn([
                'host', 'final_url', 'http_status', 'error_code', 'error_message',
                'report_version', 'started_at', 'finished_at',
            ]);
            $table->string('url')->change();
        });
    }
};
