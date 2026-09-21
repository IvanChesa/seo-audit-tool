<?php

namespace Tests\Feature\Jobs;

use App\Analysis\SectionRecorder;
use App\Analysis\SnapshotStore;
use App\Enums\Section;
use App\Enums\SectionStatus;
use App\Jobs\RunAnalyzerJob;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class RunAnalyzerJobTest extends TestCase
{
    use RefreshDatabase;

    private function runSection(Audit $audit, Section $section): void
    {
        (new RunAnalyzerJob($audit->id, $section))->handle(
            $this->app->make(SnapshotStore::class),
            $this->app->make(SectionRecorder::class),
            $this->app,
        );
    }

    private function processingAuditWithSnapshot(string $html): Audit
    {
        $audit = Audit::factory()->forUrl('https://example.com/')->processing()->create();
        $this->app->make(SnapshotStore::class)->put($audit->id, $this->snapshot($html));

        return $audit;
    }

    public function test_it_records_the_section_result(): void
    {
        $audit = $this->processingAuditWithSnapshot($this->fixture('bad-page.html'));

        $this->runSection($audit, Section::Meta);

        $result = $audit->results()->sole();
        $this->assertSame('meta', $result->type);
        $this->assertSame(SectionStatus::Completed, $result->status);
        $this->assertSame(15, $result->score);
        $this->assertSame('noindex', $result->issues[0]['code']);
        $this->assertArrayHasKey('checks', $result->data);
    }

    public function test_running_the_same_job_twice_does_not_duplicate_results(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $audit = $this->processingAuditWithSnapshot('<html><body><a href="/roto">Roto</a></body></html>');

        $this->runSection($audit, Section::Links);
        $this->runSection($audit, Section::Links);

        $this->assertSame(1, $audit->results()->count());
        $this->assertSame(1, $audit->brokenLinks()->count());
    }

    public function test_an_expired_snapshot_marks_the_section_as_failed(): void
    {
        $audit = Audit::factory()->processing()->create();

        $this->runSection($audit, Section::Headings);

        $result = $audit->results()->sole();
        $this->assertSame(SectionStatus::Failed, $result->status);
        $this->assertSame('snapshot_expired', $result->error_code);
    }

    public function test_nothing_is_done_for_finished_or_deleted_audits(): void
    {
        $finished = Audit::factory()->completed()->create();
        $this->app->make(SnapshotStore::class)->put($finished->id, $this->snapshot('<html></html>'));
        $deleted = Audit::factory()->create();
        $deleted->delete();

        $this->runSection($finished, Section::Meta);
        $this->runSection($deleted, Section::Meta);

        $this->assertSame(0, AuditResult::query()->count());
    }

    public function test_failed_records_a_failed_section_after_the_last_attempt(): void
    {
        $audit = Audit::factory()->processing()->create();

        (new RunAnalyzerJob($audit->id, Section::Content))->failed(new RuntimeException('boom'));

        $result = $audit->results()->sole();
        $this->assertSame(SectionStatus::Failed, $result->status);
        $this->assertSame('analysis_failed', $result->error_code);
        $this->assertSame('No se pudo completar el análisis de «Contenido».', $result->error_message);
    }

    public function test_a_late_failure_never_overwrites_a_successful_result(): void
    {
        $audit = $this->processingAuditWithSnapshot($this->fixture('good-page.html'));
        $this->runSection($audit, Section::Headings);

        (new RunAnalyzerJob($audit->id, Section::Headings))->failed(new RuntimeException('late timeout'));

        $this->assertSame(SectionStatus::Completed, $audit->results()->sole()->status);
    }

    public function test_the_job_timeout_depends_on_the_section(): void
    {
        $this->assertSame(150, (new RunAnalyzerJob(1, Section::Links))->timeout);
        $this->assertSame(30, (new RunAnalyzerJob(1, Section::Meta))->timeout);
        $this->assertLessThan(config('queue.connections.redis.retry_after'), Section::Links->timeout());
    }
}
