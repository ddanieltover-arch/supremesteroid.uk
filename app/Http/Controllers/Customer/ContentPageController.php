<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\Page;
use App\Services\Catalogue\SeoService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function __invoke(Request $request, string $slug, SeoService $seo): Response
    {
        $allowed = collect(config('homepage.policy_pages', []))->pluck('slug')->all();
        abort_unless(in_array($slug, $allowed, true), 404);

        $page = Page::query()->where('slug', $slug)->first();
        $title = $page?->title ?? collect(config('homepage.policy_pages', []))->firstWhere('slug', $slug)['title'] ?? 'Information';
        $hasApprovedContent = (bool) $page?->is_published && filled($page?->content);

        return Inertia::render('Content/Show', [
            'page' => [
                'title' => $title,
                'slug' => $slug,
                'content' => $hasApprovedContent ? strip_tags((string) $page->content, '<p><br><strong><em><ul><ol><li><a><h2><h3>') : null,
                'is_placeholder' => ! $hasApprovedContent,
            ],
            'seo' => $seo->forPage(
                $request,
                $title.' | '.config('store.name', 'Supreme Steroids'),
                $hasApprovedContent
                    ? strip_tags((string) $page->content)
                    : $title.' information will appear here once the store publishes approved content.',
                ['robots' => $hasApprovedContent ? 'index,follow' : 'noindex,nofollow']
            ),
        ]);
    }
}
