<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Retried jobs used to insert the same section twice. Keep the newest
        // row of each (audit, type) pair so the unique index can be created.
        $latestIds = DB::table('audit_results')
            ->selectRaw('MAX(id) as id')
            ->groupBy('audit_id', 'type')
            ->pluck('id');

        DB::table('audit_results')->whereNotIn('id', $latestIds)->delete();

        Schema::table('audit_results', function (Blueprint $table) {
            $table->string('type', 32)->change();
            $table->string('status', 16)->default('completed')->after('type');
            $table->json('issues')->nullable()->after('data');
            $table->string('error_code', 64)->nullable()->after('score');
            $table->string('error_message')->nullable()->after('error_code');

            // One row per section: job retries update it instead of duplicating it.
            $table->unique(['audit_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('audit_results', function (Blueprint $table) {
            // MySQL silently dropped the implicit foreign-key index when the
            // composite unique index was added, so the key needs a plain index
            // again before the unique one can go.
            $table->index('audit_id');
            $table->dropUnique(['audit_id', 'type']);
            $table->dropColumn(['status', 'issues', 'error_code', 'error_message']);
            $table->string('type')->change();
        });
    }
};
