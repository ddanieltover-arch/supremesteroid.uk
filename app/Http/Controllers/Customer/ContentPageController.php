<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\Page;
use App\Services\Catalogue\SeoService;
use App\Services\Content\BlogPostContentFormatter;
use App\Services\Storefront\AnswerCapsuleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class ContentPageController extends Controller
{
    public function __construct(
        protected BlogPostContentFormatter $contentFormatter,
        protected AnswerCapsuleService $answerCapsules,
    ) {}

    public function __invoke(Request $request, string $slug, SeoService $seo): Response
    {
        $cached = Cache::remember("content.page.v2.{$slug}", 600, function () use ($slug) {
            $page = Page::query()->where('slug', $slug)->first();
            $allowedConfig = collect(config('homepage.policy_pages', []))->pluck('slug')->all();
            if (! (($page && $page->is_published) || in_array($slug, $allowedConfig, true))) {
                return null;
            }

            $title = $page?->title ?? collect(config('homepage.policy_pages', []))->firstWhere('slug', $slug)['title'] ?? 'Information';
            $hasApprovedContent = (bool) $page?->is_published && filled($page?->content);

            return [
                'title' => $title,
                'slug' => $slug,
                'content' => $hasApprovedContent ? $this->contentFormatter->format((string) $page->content) : null,
                'is_placeholder' => ! $hasApprovedContent,
                'raw_content' => (string) ($page?->content ?? ''),
            ];
        });

        abort_unless($cached !== null, 404);

        return Inertia::render('Content/Show', [
            'page' => [
                'title' => $cached['title'],
                'slug' => $cached['slug'],
                'content' => $cached['content'],
                'is_placeholder' => $cached['is_placeholder'],
            ],
            'answer_capsule' => $this->answerCapsules->contentPage(
                (string) $cached['title'],
                ! $cached['is_placeholder'] ? (string) $cached['raw_content'] : null
            ),
            'seo' => $seo->forPage(
                $request,
                $cached['title'].' | '.config('store.name', 'Supreme Steroids'),
                ! $cached['is_placeholder']
                    ? strip_tags($cached['raw_content'])
                    : $cached['title'].' information will appear here once the store publishes approved content.',
                ['robots' => ! $cached['is_placeholder'] ? 'index,follow' : 'noindex,nofollow']
            ),
        ]);
    }
}
