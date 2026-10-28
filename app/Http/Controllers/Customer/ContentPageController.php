<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\Page;
use App\Services\Catalogue\SeoService;
use App\Services\Content\BlogPostContentFormatter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function __construct(
        protected BlogPostContentFormatter $contentFormatter,
    ) {}

    public function __invoke(Request $request, string $slug, SeoService $seo): Response
    {
        $page = Page::query()->where('slug', $slug)->first();
        $allowedConfig = collect(config('homepage.policy_pages', []))->pluck('slug')->all();
        abort_unless(($page && $page->is_published) || in_array($slug, $allowedConfig, true), 404);

        $title = $page?->title ?? collect(config('homepage.policy_pages', []))->firstWhere('slug', $slug)['title'] ?? 'Information';
        $hasApprovedContent = (bool) $page?->is_published && filled($page?->content);

        return Inertia::render('Content/Show', [
            'page' => [
                'title' => $title,
                'slug' => $slug,
                'content' => $hasApprovedContent ? $this->contentFormatter->format((string) $page->content) : null,
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
