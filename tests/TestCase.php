<?php

namespace Tests;

use App\Analysis\AuditContext;
use App\Analysis\PageSnapshot;
use App\Security\HostResolver;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Tests\Support\FakeHostResolver;

abstract class TestCase extends BaseTestCase
{
    /** Public test addresses used by the fake DNS. */
    protected const PUBLIC_IP = '93.184.216.34';

    protected const OTHER_PUBLIC_IP = '151.101.1.69';

    protected FakeHostResolver $dns;

    protected function setUp(): void
    {
        parent::setUp();

        // Any HTTP request that a test did not explicitly fake fails the test.
        Http::preventStrayRequests();

        $this->dns = new FakeHostResolver([
            'example.com' => [self::PUBLIC_IP],
            'www.example.com' => [self::PUBLIC_IP],
            'other.org' => [self::OTHER_PUBLIC_IP],
        ]);
        $this->app->instance(HostResolver::class, $this->dns);
    }

    protected function fixture(string $name): string
    {
        return (string) file_get_contents(__DIR__.'/Fixtures/'.$name);
    }

    /**
     * @param  array<string, mixed>  $overrides  PageSnapshot constructor arguments.
     */
    protected function snapshot(string $html, array $overrides = []): PageSnapshot
    {
        return new PageSnapshot(...[
            'requestedUrl' => 'https://example.com/',
            'finalUrl' => 'https://example.com/',
            'statusCode' => 200,
            'contentType' => 'text/html; charset=utf-8',
            'xRobotsTag' => null,
            'html' => $html,
            'redirects' => [],
            'responseTimeMs' => 250,
            ...$overrides,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function context(string $html, array $overrides = []): AuditContext
    {
        return new AuditContext(1, $this->snapshot($html, $overrides));
    }
}
