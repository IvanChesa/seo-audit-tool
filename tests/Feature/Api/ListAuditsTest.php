<?php

namespace Tests\Feature\Api;

use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListAuditsTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_paginates_audits_newest_first(): void
    {
        $audits = Audit::factory()->count(12)->create();

        $response = $this->getJson('/api/audits?per_page=5&page=2');

        $response->assertOk()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('meta.total', 12)
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 3)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('data.0.id', $audits[6]->id)
            ->assertJsonStructure(['data' => [['id', 'url', 'host', 'status', 'score', 'score_rating', 'error', 'created_at', 'finished_at']], 'links', 'meta']);

        $this->assertStringContainsString('per_page=5', (string) $response->json('links.next'));
    }

    public function test_the_summary_does_not_include_the_full_report(): void
    {
        Audit::factory()->completed(92)->create();

        $this->getJson('/api/audits')
            ->assertOk()
            ->assertJsonPath('data.0.score', 92)
            ->assertJsonPath('data.0.score_rating', 'good')
            ->assertJsonMissingPath('data.0.sections');
    }

    public function test_it_filters_by_status_and_by_url(): void
    {
        Audit::factory()->forUrl('https://shop.example.com/')->completed()->create();
        Audit::factory()->forUrl('https://blog.example.com/')->failed()->create();
        Audit::factory()->forUrl('https://blog.other.org/')->completed()->create();

        $this->getJson('/api/audits?status=completed')->assertJsonCount(2, 'data');
        $this->getJson('/api/audits?search=blog')->assertJsonCount(2, 'data');
        $this->getJson('/api/audits?status=completed&search=blog')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.url', 'https://blog.other.org/');
    }

    public function test_like_wildcards_in_the_search_are_escaped(): void
    {
        Audit::factory()->forUrl('https://example.com/')->create();

        $this->getJson('/api/audits?search=%25')->assertJsonCount(0, 'data');
    }

    public function test_failed_audits_expose_their_reason(): void
    {
        Audit::factory()->failed('http_error', 'La página respondió con el código HTTP 404.')->create();

        $this->getJson('/api/audits')
            ->assertJsonPath('data.0.error', ['code' => 'http_error', 'message' => 'La página respondió con el código HTTP 404.']);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->getJson('/api/audits?status=deleted')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson('/api/audits?per_page=500')->assertUnprocessable()->assertJsonValidationErrors('per_page');
        $this->getJson('/api/audits?page=0')->assertUnprocessable()->assertJsonValidationErrors('page');
    }

    public function test_an_empty_history(): void
    {
        $this->getJson('/api/audits')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.total', 0);
    }
}
