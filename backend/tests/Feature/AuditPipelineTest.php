<?php

namespace Tests\Feature;

use App\Analysis\SnapshotStore;
use App\Enums\AuditStatus;
use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * End to end with the sync queue: API → FetchPageJob → batch of analyzer
 * jobs → finalizer → API report. Only the network is faked.
 */
class AuditPipelineTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_audit_runs_from_request_to_report(): void
    {
        Http::fake([
            'https://example.com/' => Http::response($this->fixture('good-page.html'), 200, ['Content-Type' => 'text/html; charset=utf-8']),
            'https://example.com/robots.txt' => Http::response("User-agent: *\nDisallow:\nSitemap: https://example.com/sitemap.xml", 200, ['Content-Type' => 'text/plain']),
            'https://example.com/sitemap.xml' => Http::response('<urlset></urlset>', 200, ['Content-Type' => 'application/xml']),
            'https://example.com/contacto/' => Http::response('', 404),
            '*' => Http::response('', 200),
        ]);

        $created = $this->postJson('/api/audits', ['url' => 'example.com'])->assertCreated();
        $report = $this->getJson('/api/audits/'.$created->json('data.id'))->assertOk();

        $report->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.http_status', 200)
            ->assertJsonPath('data.progress.percentage', 100);

        $sections = array_column($report->json('data.sections'), 'status', 'key');
        $this->assertSame([
            'technical' => 'completed', 'meta' => 'completed', 'headings' => 'completed',
            'content' => 'completed', 'links' => 'completed', 'performance' => 'skipped',
        ], $sections);

        // Only the broken footer link is a problem on the good fixture.
        $this->assertSame(['broken_internal_links'], array_column($report->json('data.issues'), 'code'));
        // Links section: 100 − 20 (high) = 80; the rest score 100; performance is excluded.
        // (25·100 + 20·100 + 15·100 + 15·100 + 15·80) / 90 = 96.7 → 97
        $report->assertJsonPath('data.score', 97)
            ->assertJsonPath('data.score_rating', 'good')
            ->assertJsonPath('data.sections.4.data.broken_links.0.url', 'https://example.com/contacto/');

        // The downloaded HTML does not outlive the audit.
        $this->assertNull($this->app->make(SnapshotStore::class)->get($created->json('data.id')));
    }

    public function test_an_unreachable_page_ends_as_a_failed_audit_with_a_reason(): void
    {
        Http::fake(['*' => Http::response('<html>Not found</html>', 404, ['Content-Type' => 'text/html'])]);

        $id = $this->postJson('/api/audits', ['url' => 'https://example.com/no-existe'])->json('data.id');

        $this->getJson("/api/audits/{$id}")
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error.code', 'http_error')
            ->assertJsonPath('data.score', null);
        $this->assertSame(AuditStatus::Failed, Audit::query()->findOrFail($id)->status);
    }
}
