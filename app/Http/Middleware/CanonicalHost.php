<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->environment('production')) {
            return $next($request);
        }

        $canonical = parse_url((string) config('app.url'), PHP_URL_HOST);
        $host = $request->getHost();

        if (! is_string($canonical) || $canonical === '' || $host === $canonical) {
            return $next($request);
        }

        if ($host === 'www.'.$canonical || $host === 'www.supremesteroid.uk') {
            return redirect()->away('https://'.$canonical.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
