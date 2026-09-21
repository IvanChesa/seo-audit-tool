<?php

namespace Tests\Unit\Enums;

use App\Enums\AuditStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AuditStatusTest extends TestCase
{
    /**
     * @return array<string, array{AuditStatus, AuditStatus, bool}>
     */
    public static function transitions(): array
    {
        return [
            'pending → processing' => [AuditStatus::Pending, AuditStatus::Processing, true],
            'pending → failed' => [AuditStatus::Pending, AuditStatus::Failed, true],
            'pending → completed' => [AuditStatus::Pending, AuditStatus::Completed, false],
            'processing → completed' => [AuditStatus::Processing, AuditStatus::Completed, true],
            'processing → failed' => [AuditStatus::Processing, AuditStatus::Failed, true],
            'processing → pending' => [AuditStatus::Processing, AuditStatus::Pending, false],
            'processing → processing' => [AuditStatus::Processing, AuditStatus::Processing, false],
            'completed → processing' => [AuditStatus::Completed, AuditStatus::Processing, false],
            'completed → failed' => [AuditStatus::Completed, AuditStatus::Failed, false],
            'failed → processing' => [AuditStatus::Failed, AuditStatus::Processing, false],
            'failed → completed' => [AuditStatus::Failed, AuditStatus::Completed, false],
        ];
    }

    #[DataProvider('transitions')]
    public function test_transition_rules(AuditStatus $from, AuditStatus $to, bool $allowed): void
    {
        $this->assertSame($allowed, $from->canTransitionTo($to));
    }

    public function test_only_completed_and_failed_are_finished(): void
    {
        $this->assertFalse(AuditStatus::Pending->isFinished());
        $this->assertFalse(AuditStatus::Processing->isFinished());
        $this->assertTrue(AuditStatus::Completed->isFinished());
        $this->assertTrue(AuditStatus::Failed->isFinished());
    }

    public function test_allowed_sources_for_each_target(): void
    {
        $this->assertSame([AuditStatus::Pending], AuditStatus::allowedSourcesFor(AuditStatus::Processing));
        $this->assertSame([AuditStatus::Processing], AuditStatus::allowedSourcesFor(AuditStatus::Completed));
        $this->assertSame([AuditStatus::Pending, AuditStatus::Processing], AuditStatus::allowedSourcesFor(AuditStatus::Failed));
        $this->assertSame([], AuditStatus::allowedSourcesFor(AuditStatus::Pending));
    }
}
