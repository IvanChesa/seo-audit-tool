<?php

namespace Tests\Feature\Analysis;

use App\Analysis\Analyzers\PerformanceAnalyzer;
use App\Analysis\SectionResult;
use App\Analysis\Support\PageSpeedClient;
use App\Analysis\Support\PageSpeedException;
use App\Enums\SectionStatus;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PerformanceAnalyzerTest extends TestCase
{
    private function analyze(): SectionResult
    {
        return $this->app->make(PerformanceAnalyzer::class)->analyze($this->context('<html></html>'));
    }

    private function configureKey(): void
    {
        config(['services.pagespeed.key' => 'test-key-not-real']);
    }

    /**
     * @return array<string, mixed>
     */
    private function lighthouse(float $score, float $lcp = 1800, float $cls = 0.05): array
    {
        return ['lighthouseResult' => [
            'categories' => ['performance' => ['score' => $score]],
            'audits' => [
                'largest-contentful-paint' => ['numericValue' => $lcp, 'displayValue' => ($lcp / 1000).' s'],
                'cumulative-layout-shift' => ['numericValue' => $cls, 'displayValue' => (string) $cls],
                'first-contentful-paint' => ['numericValue' => 900, 'displayValue' => '0,9 s'],
                'total-blocking-time' => ['numericValue' => 120, 'displayValue' => '120 ms'],
                // speed-index intentionally missing: incomplete responses must not break the report.
            ],
        ]];
    }

    public function test_it_is_skipped_when_no_api_key_is_configured(): void
    {
        Http::fake();

        $result = $this->analyze();

        $this->assertSame(SectionStatus::Skipped, $result->status);
        $this->assertSame('pagespeed_not_configured', $result->errorCode);
        $this->assertNull($result->score);
        Http::assertNothingSent();
    }

    public function test_the_lighthouse_score_becomes_the_section_score(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response($this->lighthouse(0.93))]);

        $result = $this->analyze();

        $this->assertSame(SectionStatus::Completed, $result->status);
        $this->assertSame(93, $result->score);
        $this->assertSame([], $result->issues);
        $this->assertSame('good', $result->data['metrics']['lcp']['rating']);
        $this->assertNull($result->data['metrics']['speed_index']['value']);
    }

    public function test_the_api_key_travels_in_a_header_and_never_in_the_url(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response($this->lighthouse(0.9))]);

        $this->analyze();

        Http::assertSent(fn (Request $request) => $request->hasHeader('X-Goog-Api-Key', 'test-key-not-real')
            && ! str_contains($request->url(), 'test-key-not-real')
            && $request['url'] === 'https://example.com/'
            && $request['strategy'] === 'mobile');
    }

    public function test_poor_performance_and_poor_metrics_raise_issues(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response($this->lighthouse(0.35, lcp: 5200, cls: 0.4))]);

        $result = $this->analyze();

        $this->assertSame(35, $result->score);
        $this->assertSame(['poor_performance', 'poor_lcp', 'poor_cls'], array_map(fn ($issue) => $issue->code, $result->issues));
    }

    public function test_non_retryable_api_errors_mark_the_section_as_failed(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response(['error' => ['message' => 'API key not valid']], 400)]);

        $result = $this->analyze();

        $this->assertSame(SectionStatus::Failed, $result->status);
        $this->assertSame('pagespeed_rejected', $result->errorCode);
        $this->assertStringNotContainsString('test-key-not-real', (string) $result->errorMessage);
    }

    public function test_quota_errors_are_reported_without_retrying(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response([], 429)]);

        $this->assertSame('pagespeed_quota', $this->analyze()->errorCode);
    }

    public function test_transient_errors_are_thrown_so_the_job_is_retried(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response([], 503)]);

        $this->expectException(PageSpeedException::class);

        $this->analyze();
    }

    public function test_a_response_without_lighthouse_data_is_retryable(): void
    {
        $this->configureKey();
        Http::fake([PageSpeedClient::API_URL.'*' => Http::response(['kind' => 'pagespeedonline#result'])]);

        try {
            $this->analyze();
            $this->fail('Expected an exception.');
        } catch (PageSpeedException $e) {
            $this->assertTrue($e->retryable);
        }
    }
}
