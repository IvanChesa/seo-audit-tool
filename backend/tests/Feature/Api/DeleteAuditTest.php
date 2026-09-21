<?php

namespace Tests\Feature\Api;

use App\Analysis\SnapshotStore;
use App\Enums\Section;
use App\Models\Audit;
use App\Models\AuditResult;
use App\Models\BrokenLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_deletes_the_audit_and_all_its_related_data(): void
    {
        $audit = Audit::factory()->processing()->create();
        $other = Audit::factory()->completed()->create();
        AuditResult::factory()->for($audit)->section(Section::Links)->create();
        AuditResult::factory()->for($other)->section(Section::Links)->create();
        $audit->brokenLinks()->create(['url' => 'https://example.com/x', 'status_code' => 404]);
        $this->app->make(SnapshotStore::class)->put($audit->id, $this->snapshot('<html></html>'));

        $this->deleteJson("/api/audits/{$audit->id}")->assertNoContent();

        $this->assertModelMissing($audit);
        $this->assertSame(0, AuditResult::query()->where('audit_id', $audit->id)->count());
        $this->assertSame(0, BrokenLink::query()->where('audit_id', $audit->id)->count());
        $this->assertNull($this->app->make(SnapshotStore::class)->get($audit->id));
        // Other audits are not affected.
        $this->assertModelExists($other);
        $this->assertSame(1, $other->results()->count());
    }

    public function test_deleting_a_missing_audit_returns_404(): void
    {
        $this->deleteJson('/api/audits/12345')->assertNotFound();
    }
}
