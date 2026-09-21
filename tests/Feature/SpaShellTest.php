<?php

namespace Tests\Feature;

use Illuminate\Foundation\Vite;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SpaShellTest extends TestCase
{
    private const SPA_POLICY = "default-src 'self'; img-src 'self' data:; object-src 'none'; "
        ."base-uri 'none'; form-action 'self'; frame-ancestors 'none'";

    /** @return array<string, array{string}> */
    public static function frontendPaths(): array
    {
        return [
            'home' => ['/'],
            'report' => ['/audits/7'],
            'history' => ['/history?page=2'],
            'unknown page' => ['/pagina-inexistente'],
        ];
    }

    #[DataProvider('frontendPaths')]
    public function test_frontend_paths_return_the_react_shell(string $path): void
    {
        $this->fakeVite(hot: false);

        $response = $this->get($path)
            ->assertOk()
            ->assertSee('<div id="root"></div>', false)
            ->assertHeader('Content-Security-Policy', self::SPA_POLICY)
            ->assertHeader('X-Frame-Options', 'DENY');

        // The shell is static: no session or CSRF cookies.
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_the_policy_is_left_out_while_the_vite_dev_server_serves_the_assets(): void
    {
        $this->fakeVite(hot: true);

        $this->get('/')
            ->assertOk()
            ->assertHeaderMissing('Content-Security-Policy');
    }

    public function test_unknown_api_paths_still_answer_with_json(): void
    {
        $this->get('/api/no-existe')
            ->assertNotFound()
            ->assertExactJson(['message' => 'El recurso solicitado no existe.'])
            ->assertHeader('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
    }

    public function test_the_health_check_is_not_taken_by_the_shell(): void
    {
        $this->get('/up')
            ->assertOk()
            ->assertDontSee('<div id="root"></div>', false);
    }

    /** Renders the shell without a Vite build or dev server. */
    private function fakeVite(bool $hot): void
    {
        $this->swap(Vite::class, new class($hot) extends Vite
        {
            public function __construct(private readonly bool $hot) {}

            public function __invoke($entrypoints, $buildDirectory = null): HtmlString
            {
                return new HtmlString('');
            }

            public function reactRefresh(): ?HtmlString
            {
                return null;
            }

            public function isRunningHot(): bool
            {
                return $this->hot;
            }
        });
    }
}
