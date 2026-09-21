<?php

namespace Tests\Feature\Api;

use App\Enums\Section;
use App\Models\Audit;
use App\Models\AuditResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowAuditTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string|null>
     */
    private function issue(string $code, string $severity): array
    {
        return ['code' => $code, 'severity' => $severity, 'title' => $code, 'evidence' => null, 'recommendation' => 'Arréglalo.'];
    }

    public function test_a_completed_audit_returns_the_full_report(): void
    {
        $audit = Audit::factory()->completed(76)->create();
        AuditResult::factory()->for($audit)->section(Section::Technical, 100)->create();
        AuditResult::factory()->for($audit)->section(Section::Meta, 70)
            ->withIssues([$this->issue('missing_canonical', 'medium'), $this->issue('incomplete_open_graph', 'low')])
            ->create();
        AuditResult::factory()->for($audit)->section(Section::Headings, 80)->withIssues([$this->issue('missing_h1', 'high')])->create();
        AuditResult::factory()->for($audit)->section(Section::Content, 90)->create();
        AuditResult::factory()->for($audit)->section(Section::Links, 60)->withIssues([$this->issue('broken_internal_links', 'high')])->create();
        AuditResult::factory()->for($audit)->section(Section::Performance)->skipped()->create();
        $audit->brokenLinks()->create(['url' => 'https://example.com/roto', 'is_internal' => true, 'status_code' => 404, 'link_text' => 'Roto']);

        $response = $this->getJson("/api/audits/{$audit->id}");

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.score', 76)
            ->assertJsonPath('data.score_rating', 'needs_improvement')
            ->assertJsonPath('data.legacy', false)
            ->assertJsonPath('data.progress.percentage', 100)
            ->assertJsonPath('data.issues_summary', ['critical' => 0, 'high' => 2, 'medium' => 1, 'low' => 1, 'total' => 4])
            ->assertJsonCount(6, 'data.sections')
            ->assertJsonPath('data.sections.0.key', 'technical')
            ->assertJsonPath('data.sections.4.data.broken_links.0', [
                'url' => 'https://example.com/roto', 'is_internal' => true, 'status_code' => 404, 'error' => null, 'link_text' => 'Roto',
            ])
            ->assertJsonPath('data.sections.5.status', 'skipped')
            ->assertJsonPath('data.sections.5.error.code', 'pagespeed_not_configured')
            ->assertJsonPath('data.score_breakdown.sections.5.counted', false)
            ->assertJsonPath('data.score_breakdown.sections.5.effective_weight', 0)
            ->assertJsonPath('data.score_breakdown.critical_cap_applied', false);

        // Issues from every section, most severe first, keeping section order on ties.
        $this->assertSame(
            ['missing_h1', 'broken_internal_links', 'missing_canonical', 'incomplete_open_graph'],
            array_column($response->json('data.issues'), 'code'),
        );
        $this->assertSame('Encabezados', $response->json('data.issues.0.section_label'));
    }

    public function test_the_progress_of_a_running_audit(): void
    {
        $audit = Audit::factory()->processing()->create(['http_status' => 200]);
        AuditResult::factory()->for($audit)->section(Section::Meta, 90)->create();
        AuditResult::factory()->for($audit)->section(Section::Headings)->failed()->create();

        $response = $this->getJson("/api/audits/{$audit->id}")->assertOk();

        $this->assertSame([
            'fetch' => 'completed', 'technical' => 'running', 'meta' => 'completed', 'headings' => 'failed',
            'content' => 'running', 'links' => 'running', 'performance' => 'running',
        ], array_column($response->json('data.progress.steps'), 'status', 'key'));
        $response->assertJsonPath('data.progress.completed_steps', 3)->assertJsonPath('data.progress.percentage', 43);
    }

    public function test_a_failed_audit_explains_why(): void
    {
        $audit = Audit::factory()->failed('unsafe_redirect', 'La página redirige a una dirección que no se puede auditar.')->create();

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error.code', 'unsafe_redirect')
            ->assertJsonPath('data.progress.steps.0.status', 'failed')
            ->assertJsonPath('data.sections.0.status', 'not_run');
    }

    public function test_audits_from_the_previous_version_are_flagged_as_legacy(): void
    {
        $audit = Audit::factory()->legacy()->completed(64)->create();
        AuditResult::factory()->for($audit)->create(['type' => 'keywords', 'data' => ['top_keywords' => []]]);

        $this->getJson("/api/audits/{$audit->id}")
            ->assertOk()
            ->assertJsonPath('data.legacy', true)
            ->assertJsonPath('data.score', 64)
            ->assertJsonPath('data.sections', [])
            ->assertJsonPath('data.progress', null);
    }

    public function test_missing_audits_return_a_generic_404(): void
    {
        $this->getJson('/api/audits/999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'El recurso solicitado no existe.']);

        $this->getJson('/api/audits/abc')->assertNotFound();
    }

    public function test_unsupported_methods_return_json(): void
    {
        $audit = Audit::factory()->create();

        $this->putJson("/api/audits/{$audit->id}", [])
            ->assertMethodNotAllowed()
            ->assertJsonPath('message', 'Método HTTP no permitido para esta ruta.');
    }
}
