<?php

namespace Tests\Feature\Api;

use App\Jobs\FetchPageJob;
use App\Models\Audit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CreateAuditTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_it_creates_a_pending_audit_and_queues_it(): void
    {
        $response = $this->postJson('/api/audits', ['url' => 'https://Example.com/blog?page=2#comentarios']);

        $audit = Audit::query()->sole();

        $response->assertCreated()
            ->assertHeader('Location', route('audits.show', $audit))
            ->assertJsonPath('data.id', $audit->id)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.url', 'https://example.com/blog?page=2')
            ->assertJsonPath('data.host', 'example.com')
            ->assertJsonPath('data.score', null)
            ->assertJsonPath('data.progress.completed_steps', 0)
            ->assertJsonPath('data.progress.steps.0', ['key' => 'fetch', 'label' => 'Descarga de la página', 'status' => 'pending']);

        Queue::assertPushed(FetchPageJob::class, fn (FetchPageJob $job) => $job->auditId === $audit->id);
    }

    public function test_a_bare_domain_is_audited_over_https(): void
    {
        $this->postJson('/api/audits', ['url' => '  www.example.com  '])
            ->assertCreated()
            ->assertJsonPath('data.url', 'https://www.example.com/');
    }

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function invalidUrls(): array
    {
        return [
            'missing' => [null, 'Introduce la URL de la página que quieres auditar.'],
            'not a string' => [['https://example.com'], 'La URL no tiene un formato válido.'],
            'too long' => ['https://example.com/'.str_repeat('a', 2050), 'La URL no puede superar los 2048 caracteres.'],
            'ftp' => ['ftp://example.com/', 'Solo se admiten direcciones que empiecen por http:// o https://.'],
            'file' => ['file:///etc/passwd', 'Solo se admiten direcciones que empiecen por http:// o https://.'],
            'javascript' => ['javascript:alert(document.cookie)', 'Solo se admiten direcciones que empiecen por http:// o https://.'],
            'credentials' => ['https://admin:secret@example.com/', 'La URL no puede incluir usuario ni contraseña.'],
            'localhost' => ['http://localhost:8080/', 'No se pueden auditar direcciones locales o de redes internas.'],
            'Docker service name' => ['http://mysql:3306/', 'No se pueden auditar direcciones locales o de redes internas.'],
            'non-web port' => ['https://example.com:6379/', 'El puerto de la URL no está permitido. Usa los puertos web habituales.'],
            'loopback' => ['http://127.0.0.1/', 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/', 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.'],
            'private network' => ['https://10.0.0.1/', 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.'],
            'IPv6 loopback' => ['http://[::1]/', 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.'],
            'decimal IP' => ['http://2130706433/', 'El dominio de la URL no es válido.'],
            'domain resolving to a private IP' => ['https://intranet.attacker.com/', 'La URL apunta a una dirección IP privada, reservada o interna y no se puede auditar.'],
            'unknown domain' => ['https://no-existe.example.net/', 'No se ha podido resolver el dominio. Comprueba que existe y es público.'],
        ];
    }

    #[DataProvider('invalidUrls')]
    public function test_invalid_and_dangerous_urls_are_rejected(mixed $url, string $message): void
    {
        $this->dns->set('intranet.attacker.com', ['192.168.1.20']);

        $this->postJson('/api/audits', ['url' => $url])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['url' => $message]);

        $this->assertSame(0, Audit::query()->count());
        Queue::assertNothingPushed();
    }

    public function test_audit_creation_is_rate_limited_per_client(): void
    {
        config(['seo-audit.rate_limit.create_per_minute' => 2]);

        $this->postJson('/api/audits', ['url' => 'https://example.com/1'])->assertCreated();
        $this->postJson('/api/audits', ['url' => 'https://example.com/2'])->assertCreated();

        $this->postJson('/api/audits', ['url' => 'https://example.com/3'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After')
            ->assertJsonPath('message', 'Has realizado demasiadas solicitudes. Espera un momento y vuelve a intentarlo.');

        $this->assertSame(2, Audit::query()->count());
    }

    public function test_cors_only_allows_the_configured_origins(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:5173', 'https://seo.example.com']]);

        $preflight = fn (string $origin) => $this->call('OPTIONS', '/api/audits', server: [
            'HTTP_ORIGIN' => $origin,
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type',
        ]);

        $preflight('http://localhost:5173')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173');

        $preflight('https://seo.example.com')
            ->assertHeader('Access-Control-Allow-Origin', 'https://seo.example.com');

        // An unknown origin is never echoed back, so the browser blocks the call.
        $this->assertNotSame(
            'https://evil.example',
            $preflight('https://evil.example')->headers->get('Access-Control-Allow-Origin'),
        );
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->postJson('/api/audits', ['url' => 'https://example.com/'])
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'no-referrer');
    }
}
