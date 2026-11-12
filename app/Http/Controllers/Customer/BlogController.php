<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Models\BlogPost;
use App\Services\Catalogue\SeoService;
use App\Services\Catalogue\StructuredDataService;
use App\Services\Content\BlogPostContentFormatter;
use App\Services\Storefront\AnswerCapsuleService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class BlogController extends Controller
{
    public function __construct(
        protected SeoService $seoService,
        protected StructuredDataService $structuredData,
        protected BlogPostContentFormatter $contentFormatter,
        protected AnswerCapsuleService $answerCapsules,
    ) {}

    public function index(Request $request): Response
    {
        $search = trim((string) ($request->query('q') ?? $request->query('search') ?? ''));
        $page = (int) $request->query('page', 1);

        $fetchListing = function () use ($search) {
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

            return ['posts' => $posts, 'featured' => $featured];
        };

        $result = ($search === '' && $page === 1)
            ? Cache::remember('blog.index.v2.page1', 300, $fetchListing)
            : $fetchListing();

        return Inertia::render('Blog/Index', [
            'posts' => $result['posts'],
            'featured' => $result['featured'],
            'filters' => [
                'search' => $search,
            ],
            'answer_capsule' => $this->answerCapsules->blogIndex(),
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
        $cached = Cache::remember("blog.show.v3.{$slug}", 600, function () use ($slug) {
            $post = BlogPost::query()
                ->where('slug', $slug)
                ->where('is_published', true)
                ->with('author')
                ->first();

            if (! $post) {
                return null;
            }

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
            $publishedAt = $post->published_at ?? $post->created_at;

            return [
                'post' => [
                    'id' => $post->id,
                    'title' => $post->title,
                    'slug' => $post->slug,
                    'content' => $cleanContent,
                    'excerpt' => $post->excerpt,
                    'featured_image_url' => $post->featured_image_url,
                    'published_at' => $publishedAt?->format('F d, Y') ?? '',
                    'published_at_iso' => $publishedAt?->toAtomString(),
                    'updated_at_iso' => $post->updated_at?->toAtomString() ?? $publishedAt?->toAtomString(),
                    'reading_time' => $readingTime . ' min read',
                    'word_count' => $wordCount,
                    'author' => $post->author?->name ?? 'Supreme Editorial Team',
                ],
                'recent_posts' => $recentPosts,
                'raw_content' => (string) $post->content,
            ];
        });

        abort_unless($cached !== null, 404);

        $post = $cached['post'];
        $canonical = url('/blog/'.$post['slug']);
        $description = $post['excerpt'] ?: Str::limit(strip_tags($cached['raw_content']), 160);

        $publishedAt = ! empty($post['published_at_iso'])
            ? new \DateTimeImmutable((string) $post['published_at_iso'])
            : null;
        $updatedAt = ! empty($post['updated_at_iso'])
            ? new \DateTimeImmutable((string) $post['updated_at_iso'])
            : $publishedAt;

        return Inertia::render('Blog/Show', [
            'post' => $post,
            'recent_posts' => $cached['recent_posts'],
            'answer_capsule' => $this->answerCapsules->blogPost(
                (string) $post['title'],
                $post['excerpt'] ?? $description
            ),
            'structured_data' => [
                $this->structuredData->breadcrumbs([
                    ['name' => 'Home', 'url' => route('home')],
                    ['name' => 'Articles', 'url' => route('blog.index')],
                    ['name' => $post['title'], 'url' => $canonical],
                ]),
                $this->structuredData->forBlogPost(
                    title: $post['title'],
                    description: $description,
                    url: $canonical,
                    image: $post['featured_image_url'] ?? null,
                    authorName: (string) ($post['author'] ?? 'Supreme Editorial Team'),
                    datePublished: $publishedAt,
                    dateModified: $updatedAt,
                ),
            ],
            'seo' => $this->seoService->forPage(
                $request,
                $post['title'].' | Supreme Steroids UK',
                $description,
                [
                    'canonical' => $canonical,
                    'og_image' => $post['featured_image_url'],
                ]
            ),
        ]);
    }
}
