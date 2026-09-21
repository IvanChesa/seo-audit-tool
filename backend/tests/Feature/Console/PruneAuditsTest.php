<?php

namespace Tests\Feature\Console;

use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stuck_audits_are_marked_as_failed(): void
    {
        $stuckProcessing = Audit::factory()->processing()->create(['created_at' => now()->subMinutes(30)]);
        $stuckPending = Audit::factory()->create(['created_at' => now()->subMinutes(20)]);
        $recent = Audit::factory()->processing()->create(['created_at' => now()->subMinutes(2)]);
        $finished = Audit::factory()->completed()->create(['created_at' => now()->subMinutes(30)]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertSame(AuditStatus::Failed, $stuckProcessing->fresh()->status);
        $this->assertSame('timeout', $stuckProcessing->fresh()->error_code);
        $this->assertSame(AuditStatus::Failed, $stuckPending->fresh()->status);
        $this->assertSame(AuditStatus::Processing, $recent->fresh()->status);
        $this->assertSame(AuditStatus::Completed, $finished->fresh()->status);
    }

    public function test_audits_older_than_the_retention_period_are_deleted_with_their_results(): void
    {
        config(['seo-audit.retention.days' => 30]);
        $old = Audit::factory()->completed()->create(['created_at' => now()->subDays(31)]);
        AuditResult::factory()->for($old)->section(Section::Meta)->create();
        $kept = Audit::factory()->completed()->create(['created_at' => now()->subDays(5)]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertModelMissing($old);
        $this->assertSame(0, AuditResult::query()->where('audit_id', $old->id)->count());
        $this->assertModelExists($kept);
    }

    public function test_history_is_kept_by_default(): void
    {
        $old = Audit::factory()->completed()->create(['created_at' => now()->subYears(2)]);

        $this->artisan('audits:prune')->assertSuccessful();

        $this->assertModelExists($old);
    }

    public function test_the_retention_can_be_overridden_from_the_command_line(): void
    {
        config(['seo-audit.retention.days' => 30]);
        $old = Audit::factory()->completed()->create(['created_at' => now()->subDays(40)]);

        $this->artisan('audits:prune', ['--days' => 0])->assertSuccessful();
        $this->assertModelExists($old);

        $this->artisan('audits:prune', ['--days' => 35])->assertSuccessful();
        $this->assertModelMissing($old);
    }
}
