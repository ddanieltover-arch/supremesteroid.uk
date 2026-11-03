<?php

declare(strict_types=1);

namespace App\Http\Controllers\Seo;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class RobotsController extends Controller
{
    public function __invoke(): Response
    {
        $sitemap = rtrim((string) config('store.domain', config('app.url')), '/').'/sitemap.xml';

        $body = implode("\n", [
            'User-agent: *',
            'Allow: /',
            'Disallow: /admin',
            'Disallow: /admin/',
            'Disallow: /checkout',
            'Disallow: /checkout/',
            'Disallow: /cart',
            'Disallow: /cart/',
            'Disallow: /account',
            'Disallow: /account/',
            'Disallow: /internal',
            'Disallow: /internal/',
            'Disallow: /search',
            'Disallow: /compare',
            'Disallow: /login',
            'Disallow: /register',
            'Disallow: /design-system',
            '',
            'User-agent: GPTBot',
            'Allow: /',
            '',
            'User-agent: ChatGPT-User',
            'Allow: /',
            '',
            'User-agent: ClaudeBot',
            'Allow: /',
            '',
            'User-agent: PerplexityBot',
            'Allow: /',
            '',
            'User-agent: Google-Extended',
            'Allow: /',
            '',
            'Sitemap: '.$sitemap,
            '',
        ]);

        return response($body, 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
