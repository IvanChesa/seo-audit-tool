<?php

namespace Tests\Feature\Jobs;

use App\Analysis\PageFetcher;
use App\Analysis\SnapshotStore;
use App\Enums\AuditStatus;
use App\Enums\Section;
use App\Jobs\FetchPageJob;
use App\Jobs\RunAnalyzerJob;
use App\Models\Audit;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class FetchPageJobTest extends TestCase
{
    use RefreshDatabase;

    private function runJob(Audit $audit, int $attempt = 1): FetchPageJob
    {
        $job = (new FetchPageJob($audit->id))->withFakeQueueInteractions();

        // Simulate the queue's attempt counter for retry tests.
        if ($attempt > 1) {
            $job->job->attempts = $attempt;
        }

        $job->handle($this->app->make(PageFetcher::class), $this->app->make(SnapshotStore::class));

        return $job;
    }

    public function test_it_stores_the_page_and_dispatches_one_analyzer_per_section(): void
    {
        Bus::fake();
        Http::fake(['*' => Http::response($this->fixture('good-page.html'), 200, ['Content-Type' => 'text/html'])]);
        $audit = Audit::factory()->forUrl('https://example.com/')->create();

        $this->runJob($audit);

        $audit->refresh();
        $this->assertSame(AuditStatus::Processing, $audit->status);
        $this->assertNotNull($audit->started_at);
        $this->assertSame(200, $audit->http_status);
        $this->assertSame('https://example.com/', $audit->final_url);
        $this->assertNotNull($this->app->make(SnapshotStore::class)->get($audit->id));

        Bus::assertBatched(function (PendingBatch $batch) use ($audit) {
            $sections = array_map(fn (RunAnalyzerJob $job) => $job->section, $batch->jobs->all());

            return $batch->name === "audit:{$audit->id}"
                && $batch->allowsFailures()
                && $sections === Section::cases();
        });
    }

    public function test_a_permanent_failure_fails_the_audit_immediately(): void
    {
        Bus::fake();
        Http::fake(['*' => Http::response('Not found', 404, ['Content-Type' => 'text/html'])]);
        $audit = Audit::factory()->forUrl('https://example.com/missing')->create();

        $job = $this->runJob($audit);

        $audit->refresh();
        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('http_error', $audit->error_code);
        $this->assertStringContainsString('404', (string) $audit->error_message);
        $job->assertNotReleased();
        Bus::assertNothingBatched();
    }

    public function test_a_transient_failure_releases_the_job_for_a_retry(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));
        $audit = Audit::factory()->forUrl('https://example.com/')->create();

        $job = $this->runJob($audit);

        $job->assertReleased(10);
        $this->assertSame(AuditStatus::Processing, $audit->fresh()->status);
    }

    public function test_the_last_attempt_of_a_transient_failure_fails_the_audit(): void
    {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));
        $audit = Audit::factory()->forUrl('https://example.com/')->processing()->create();

        $job = $this->runJob($audit, attempt: 3);

        $job->assertNotReleased();
        $this->assertSame(AuditStatus::Failed, $audit->fresh()->status);
        $this->assertSame('timeout', $audit->fresh()->error_code);
    }

    public function test_a_retry_of_an_audit_already_processing_continues_normally(): void
    {
        Bus::fake();
        Http::fake(['*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);
        $audit = Audit::factory()->forUrl('https://example.com/')->processing()->create();

        $this->runJob($audit, attempt: 2);

        $this->assertSame(AuditStatus::Processing, $audit->fresh()->status);
        Bus::assertBatchCount(1);
    }

    public function test_finished_or_deleted_audits_are_ignored(): void
    {
        Bus::fake();
        Http::fake();
        $finished = Audit::factory()->completed()->create();
        $deleted = Audit::factory()->create();
        $deleted->delete();

        $this->runJob($finished);
        $this->runJob($deleted);

        Http::assertNothingSent();
        Bus::assertNothingBatched();
        $this->assertSame(AuditStatus::Completed, $finished->fresh()->status);
    }

    public function test_failed_marks_an_unfinished_audit_as_failed_without_leaking_the_exception(): void
    {
        $audit = Audit::factory()->processing()->create();

        (new FetchPageJob($audit->id))->failed(new RuntimeException('SQLSTATE[HY000] secret internals'));

        $audit->refresh();
        $this->assertSame(AuditStatus::Failed, $audit->status);
        $this->assertSame('unexpected_error', $audit->error_code);
        $this->assertStringNotContainsString('SQLSTATE', (string) $audit->error_message);
    }
}
