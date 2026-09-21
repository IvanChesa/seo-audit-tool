<?php

namespace App\Providers;

use App\Security\DnsHostResolver;
use App\Security\HostResolver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        HostResolver::class => DnsHostResolver::class,
    ];

    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Read endpoints (the SPA polls the audit while it runs).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(
            (int) config('seo-audit.rate_limit.api_per_minute')
        )->by($request->ip()));

        // Creating an audit makes the server download third-party pages, so
        // it gets a much stricter budget per client IP.
        RateLimiter::for('audit-creation', fn (Request $request) => [
            Limit::perMinute((int) config('seo-audit.rate_limit.create_per_minute'))->by('minute:'.$request->ip()),
            Limit::perDay((int) config('seo-audit.rate_limit.create_per_day'))->by('day:'.$request->ip()),
        ]);
    }
}
