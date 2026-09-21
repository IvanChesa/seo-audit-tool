<?php

namespace Database\Factories;

use App\Enums\AuditStatus;
use App\Models\Audit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Test-only factory (the application never seeds fake audits).
 *
 * @extends Factory<Audit>
 */
class AuditFactory extends Factory
{
    protected $model = Audit::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $host = fake()->unique()->domainName();

        return [
            'url' => "https://{$host}/",
            'host' => $host,
            'status' => AuditStatus::Pending,
            'report_version' => Audit::REPORT_VERSION,
        ];
    }

    public function forUrl(string $url): static
    {
        return $this->state(fn () => ['url' => $url, 'host' => parse_url($url, PHP_URL_HOST)]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => AuditStatus::Processing, 'started_at' => now()]);
    }

    public function completed(int $score = 80): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => AuditStatus::Completed,
            'score' => $score,
            'http_status' => 200,
            'final_url' => $attributes['url'],
            'started_at' => now()->subMinute(),
            'finished_at' => now(),
        ]);
    }

    public function failed(string $code = 'http_error', string $message = 'La página respondió con el código HTTP 404.'): static
    {
        return $this->state(fn () => [
            'status' => AuditStatus::Failed,
            'error_code' => $code,
            'error_message' => $message,
            'finished_at' => now(),
        ]);
    }

    /**
     * An audit stored by the first version of the tool (no structured report).
     */
    public function legacy(): static
    {
        return $this->state(fn () => ['report_version' => null]);
    }
}
