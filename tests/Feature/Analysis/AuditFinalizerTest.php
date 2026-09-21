<?php

namespace Tests\Feature\Analysis;

use App\Analysis\AuditFinalizer;
use App\Analysis\ScoreCalculator;
use App\Analysis\SnapshotStore;
use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditFinalizerTest extends TestCase
{
    use RefreshDatabase;

    private function finalize(Audit $audit): Audit
    {
        $this->app->make(AuditFinalizer::class)->finalize($audit->id);

        return $audit->fresh();
    }

    public function test_it_completes_the_audit_with_the_weighted_score(): void
    {
        $audit = Audit::factory()->processing()->create();
        foreach ([Section::Technical, Section::Meta, Section::Headings, Section::Content, Section::Links] as $section) {
            AuditResult::factory()->for($audit)->section($section, 80)->create();
        }
        AuditResult::factory()->for($audit)->section(Section::Performance)->skipped()->create();
        $this->app->make(SnapshotStore::class)->put($audit->id, $this->snapshot('<html></html>'));

        $audit = $this->finalize($audit);

        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertSame(80, $audit->score);
        $this->assertNotNull($audit->finished_at);
        // Temporary data is removed as soon as the audit is closed.
        $this->assertNull($this->app->make(SnapshotStore::class)->get($audit->id));
    }

    public function test_sections_that_never_reported_are_recorded_as_failed_and_excluded(): void
    {
        $audit = Audit::factory()->processing()->create();
        AuditResult::factory()->for($audit)->section(Section::Meta, 60)->create();

        $audit = $this->finalize($audit);

        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertSame(60, $audit->score);
        $this->assertSame(count(Section::cases()), $audit->results()->count());
        $this->assertSame('analysis_incomplete', $audit->results()->where('type', 'links')->sole()->error_code);
    }

    public function test_the_audit_fails_when_no_section_produced_a_score(): void
    {
        $audit = Audit::factory()->processing()->create();
        AuditResult::factory()->for($audit)->section(Section::Meta)->failed()->create();

        $audit = $this->finalize($audit);

        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('analysis_failed', $audit->error_code);
        $this->assertNull($audit->score);
    }

    public function test_critical_issues_cap_the_global_score(): void
    {
        $audit = Audit::factory()->processing()->create();
        AuditResult::factory()->for($audit)->section(Section::Meta, 60)
            ->withIssues([['code' => 'noindex', 'severity' => 'critical', 'title' => 'x', 'evidence' => null, 'recommendation' => 'y']])
            ->create();
        AuditResult::factory()->for($audit)->section(Section::Technical, 100)->create();

        $this->assertSame(ScoreCalculator::CRITICAL_CAP, $this->finalize($audit)->score);
    }

    public function test_finalizing_twice_is_harmless(): void
    {
        $audit = Audit::factory()->processing()->create();
        AuditResult::factory()->for($audit)->section(Section::Meta, 70)->create();

        $first = $this->finalize($audit);
        AuditResult::query()->where('audit_id', $audit->id)->where('type', 'meta')->update(['score' => 10]);
        $second = $this->finalize($audit);

        $this->assertSame(70, $second->score);
        $this->assertEquals($first->finished_at, $second->finished_at);
    }

    public function test_audits_that_are_not_processing_are_left_untouched(): void
    {
        $failed = Audit::factory()->failed()->create();
        $deleted = Audit::factory()->processing()->create();
        $deleted->delete();

        $this->assertSame(AuditStatus::Failed, $this->finalize($failed)->status);
        $this->app->make(AuditFinalizer::class)->finalize($deleted->id);
        $this->assertSame(0, AuditResult::query()->where('status', SectionStatus::Failed)->count());
    }
}
