<?php

namespace Tests\Feature\Models;

use App\Enums\AuditStatus;
use App\Models\Audit;
use App\Models\Exceptions\InvalidStatusTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditStatusTransitionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_happy_path_sets_timestamps_and_score(): void
    {
        $audit = Audit::factory()->create();

        $audit->markProcessing();
        $this->assertSame(AuditStatus::Processing, $audit->status);
        $this->assertNotNull($audit->started_at);

        $audit->markCompleted(87);
        $this->assertSame(AuditStatus::Completed, $audit->status);
        $this->assertSame(87, $audit->score);
        $this->assertNotNull($audit->finished_at);
    }

    public function test_a_finished_audit_cannot_be_reopened(): void
    {
        $audit = Audit::factory()->completed()->create();

        $this->expectException(InvalidStatusTransition::class);

        $audit->markProcessing();
    }

    public function test_a_pending_audit_cannot_be_completed_directly(): void
    {
        $audit = Audit::factory()->create();

        $this->expectException(InvalidStatusTransition::class);

        $audit->markCompleted(50);
    }

    public function test_the_transition_checks_the_database_not_the_stale_model(): void
    {
        $audit = Audit::factory()->processing()->create();
        $staleCopy = Audit::query()->findOrFail($audit->id);

        // Another worker finishes the audit first.
        $audit->markCompleted(90);

        try {
            $staleCopy->markFailed('timeout', 'Tarde');
            $this->fail('The stale copy must not overwrite the finished audit.');
        } catch (InvalidStatusTransition) {
            $this->assertSame(AuditStatus::Completed, $audit->fresh()->status);
            $this->assertSame(90, $audit->fresh()->score);
        }
    }

    public function test_failure_messages_are_truncated_to_fit_the_column(): void
    {
        $audit = Audit::factory()->create();

        $audit->markFailed('http_error', str_repeat('a', 400));

        $this->assertLessThanOrEqual(255, mb_strlen((string) $audit->error_message));
    }
}
