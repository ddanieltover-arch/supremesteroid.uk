<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\BlogPost;
use App\Services\Catalogue\SeoService;
use App\Services\Content\BlogPostContentFormatter;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
        protected BlogPostContentFormatter $contentFormatter,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) ($request->query('q') ?? $request->query('search') ?? ''));

        $query = BlogPost::query()
            ->where('is_published', true)
            ->with('author')
            ->orderByDesc('published_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        $posts = $query->paginate(9)->withQueryString()
            ->through(function (BlogPost $post) {
                $wordCount = str_word_count(strip_tags((string) $post->content));
                $readingTime = max(1, (int) ceil($wordCount / 200));

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'excerpt' => $post->excerpt ?: Str::limit(strip_tags((string) $post->content), 200),
                    'featured_image_url' => $post->featured_image_url,
                    'published_at' => $post->published_at?->format('d M Y') ?? $post->created_at->format('d M Y'),
                    'reading_time' => $readingTime . ' min read',
                    'author' => $post->author?->name ?? 'Supreme Editorial Team',
                ];
            });

        $featured = null;
        if ($posts->currentPage() === 1 && $posts->isNotEmpty()) {
            $featured = $posts->items()[0];
        }

        return Inertia::render('Blog/Index', [
            'posts' => $posts,
            'featured' => $featured,
            'filters' => [
                'search' => $search,
            ],
            'seo' => $this->seoService->forPage(
                $request,
                'Educational Guides & Steroid Research Articles | Supreme Steroids',
                'Comprehensive guides on cycles, peptides, recovery protocols, nutrition, and performance compounds from UK researchers and experts.',
                ['canonical' => url('/blog')]
            ),
        ]);
    }

    public function show(Request $request, string $slug): Response
    {
        $post = BlogPost::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('author')
            ->firstOrFail();

        $wordCount = str_word_count(strip_tags((string) $post->content));
        $readingTime = max(1, (int) ceil($wordCount / 200));

        $recentPosts = BlogPost::query()
            ->where('is_published', true)
            ->where('id', '!=', $post->id)
            ->orderByDesc('published_at')
            ->limit(3)
            ->get(['id', 'title', 'slug', 'excerpt', 'featured_image_url', 'published_at'])
            ->map(fn (BlogPost $p) => [
                'id' => $p->id,
                'title' => $p->title,
                'slug' => $p->slug,
                'excerpt' => Str::limit(strip_tags((string) $p->excerpt ?: (string) $p->content), 120),
                'featured_image_url' => $p->featured_image_url,
                'published_at' => $p->published_at?->format('d M Y'),
            ]);

        $cleanContent = $this->contentFormatter->format((string) $post->content);

        return Inertia::render('Blog/Show', [
            'post' => [
                'id' => $post->id,
                'title' => $post->title,
                'slug' => $post->slug,
                'content' => $cleanContent,
                'excerpt' => $post->excerpt,
                'featured_image_url' => $post->featured_image_url,
                'published_at' => $post->published_at?->format('F d, Y') ?? $post->created_at->format('F d, Y'),
                'reading_time' => $readingTime . ' min read',
                'word_count' => $wordCount,
                'author' => $post->author?->name ?? 'Supreme Editorial Team',
            ],
            'recent_posts' => $recentPosts,
            'seo' => $this->seoService->forPage(
                $request,
                $post->title . ' | Supreme Steroids UK',
                $post->excerpt ?: Str::limit(strip_tags((string) $post->content), 160),
                [
                    'canonical' => url('/blog/' . $post->slug),
                    'og_image' => $post->featured_image_url,
                ]
            ),
        ]);
    }
}
