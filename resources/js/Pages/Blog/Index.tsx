import { Link, router } from '@inertiajs/react';
import { BookOpen, Calendar, ChevronRight, Clock, Search, Sparkles, User } from 'lucide-react';
import { useState } from 'react';
import StorefrontLayout from '../../Layouts/StorefrontLayout';
import type { SeoProps } from '@/types';

type BlogPostCard = {
  id: string;
  title: string;
  slug: string;
  excerpt: string;
  featured_image_url?: string | null;
  published_at: string;
  reading_time: string;
  author: string;
};

type PaginatedPosts = {
  data: BlogPostCard[];
  current_page: number;
  last_page: number;
  total: number;
  prev_page_url?: string | null;
  next_page_url?: string | null;
};

export default function BlogIndex({
  posts,
  featured,
  filters,
  seo,
}: {
  posts: PaginatedPosts;
  featured?: BlogPostCard | null;
  filters?: { search?: string };
  seo?: SeoProps;
}) {
  const [searchTerm, setSearchTerm] = useState(filters?.search ?? '');

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    router.get('/blog', { q: searchTerm }, { preserveState: true, replace: true });
  };

  return (
    <StorefrontLayout seo={seo}>
      <div className="mx-auto max-w-5xl">
        <nav className="mb-6 flex items-center gap-2 text-xs text-slate-500" aria-label="Breadcrumb">
          <Link href="/" className="hover:text-blue-600">Home</Link>
          <ChevronRight className="h-3 w-3 text-slate-400" aria-hidden />
          <span className="font-medium text-slate-900">Articles & Education</span>
        </nav>

        <div className="mb-8 overflow-hidden rounded-2xl bg-gradient-to-r from-slate-900 via-slate-800 to-indigo-950 p-8 text-white shadow-xl sm:p-10">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-blue-500/20 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-blue-300">
            <Sparkles className="h-3.5 w-3.5" aria-hidden />
            Articles
          </span>
          <h1 className="mt-4 text-2xl font-extrabold tracking-tight text-white sm:text-4xl">
            Guides & Research
          </h1>
          <p className="mt-3 max-w-2xl text-sm leading-relaxed text-slate-300">
            Educational articles, compound guides, and reference reading from the Supreme Steroids UK storefront.
          </p>

          <form onSubmit={handleSearch} className="mt-6 flex max-w-md items-center gap-2">
            <div className="relative flex-1">
              <Search className="absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" aria-hidden />
              <input
                type="text"
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder="Search articles & topics..."
                className="w-full rounded-xl border border-white/15 bg-white/10 py-3 pl-10 pr-4 text-sm text-white placeholder-slate-400 focus:border-blue-400 focus:outline-none focus:ring-2 focus:ring-blue-500/40"
              />
            </div>
            <button
              type="submit"
              className="rounded-xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white shadow hover:bg-blue-500 transition-colors"
            >
              Search
            </button>
          </form>
        </div>

        {/* Featured Post (if on page 1 and no search filter) */}
        {featured && !filters?.search && (
          <div className="mb-14 overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
            <div className="grid lg:grid-cols-12">
              <div className="relative aspect-[16/10] bg-slate-100 lg:col-span-7 lg:aspect-auto">
                <img
                  src={featured.featured_image_url || 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&auto=format&fit=crop&q=80'}
                  alt={featured.title}
                  className="h-full w-full object-cover"
                />
                <span className="absolute left-4 top-4 rounded-full bg-blue-600 px-3 py-1 text-xs font-bold uppercase tracking-wider text-white shadow-sm">
                  Featured Guide
                </span>
              </div>
              <div className="flex flex-col justify-center p-8 sm:p-10 lg:col-span-5">
                <div className="flex items-center gap-4 text-xs text-slate-500">
                  <span className="flex items-center gap-1">
                    <Calendar className="h-3.5 w-3.5 text-slate-400" />
                    {featured.published_at}
                  </span>
                  <span className="flex items-center gap-1">
                    <Clock className="h-3.5 w-3.5 text-slate-400" />
                    {featured.reading_time}
                  </span>
                </div>
                <h2 className="mt-4 text-2xl font-bold text-slate-900 hover:text-blue-600 transition-colors sm:text-3xl">
                  <Link href={`/blog/${featured.slug}`}>{featured.title}</Link>
                </h2>
                <p className="mt-3 text-sm leading-relaxed text-slate-600 line-clamp-3">
                  {featured.excerpt}
                </p>
                <div className="mt-6 flex items-center justify-between">
                  <div className="flex items-center gap-2 text-xs font-medium text-slate-700">
                    <User className="h-4 w-4 text-slate-400" />
                    {featured.author}
                  </div>
                  <Link
                    href={`/blog/${featured.slug}`}
                    className="inline-flex items-center gap-1 text-sm font-semibold text-blue-600 hover:text-blue-700"
                  >
                    Read Guide &rarr;
                  </Link>
                </div>
              </div>
            </div>
          </div>
        )}

        {/* Section Heading */}
        <div className="mb-8 flex items-center justify-between">
          <div>
            <h2 className="text-xl font-bold text-slate-900 sm:text-2xl">
              {filters?.search ? `Search results for "${filters.search}"` : 'All Articles & Guides'}
            </h2>
            <p className="mt-1 text-xs text-slate-500">
              Showing {posts.total} published educational resources
            </p>
          </div>
        </div>

        {/* Article Grid */}
        {posts.data.length === 0 ? (
          <div className="rounded-2xl border border-slate-200 bg-white p-12 text-center shadow-sm">
            <BookOpen className="mx-auto h-12 w-12 text-slate-300" />
            <h3 className="mt-4 text-base font-semibold text-slate-900">No articles found</h3>
            <p className="mt-2 text-sm text-slate-500">
              Try adjusting your search terms or browse our full catalogue.
            </p>
            <Link
              href="/blog"
              className="mt-6 inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700"
            >
              Reset Search
            </Link>
          </div>
        ) : (
          <div className="grid gap-8 sm:grid-cols-2 lg:grid-cols-3">
            {posts.data.map((post) => (
              <article
                key={post.id}
                className="group flex flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition-all duration-300 hover:-translate-y-1 hover:shadow-lg"
              >
                <Link href={`/blog/${post.slug}`} className="relative aspect-[16/10] overflow-hidden bg-slate-100">
                  <img
                    src={post.featured_image_url || 'https://images.unsplash.com/photo-1517838277536-f5f99be501cd?w=800&auto=format&fit=crop&q=80'}
                    alt={post.title}
                    className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                  />
                  <div className="absolute bottom-3 left-3 flex items-center gap-2 rounded-lg bg-slate-900/80 px-2.5 py-1 text-xs font-medium text-white backdrop-blur">
                    <Clock className="h-3 w-3" />
                    {post.reading_time}
                  </div>
                </Link>

                <div className="flex flex-1 flex-col p-6">
                  <div className="flex items-center gap-3 text-xs text-slate-500">
                    <span className="flex items-center gap-1">
                      <Calendar className="h-3.5 w-3.5 text-slate-400" />
                      {post.published_at}
                    </span>
                    <span>•</span>
                    <span>{post.author}</span>
                  </div>

                  <h3 className="mt-3 text-lg font-bold text-slate-900 group-hover:text-blue-600 transition-colors line-clamp-2">
                    <Link href={`/blog/${post.slug}`}>{post.title}</Link>
                  </h3>

                  <p className="mt-2.5 flex-1 text-xs leading-relaxed text-slate-600 line-clamp-3">
                    {post.excerpt}
                  </p>

                  <div className="mt-6 border-t border-slate-100 pt-4">
                    <Link
                      href={`/blog/${post.slug}`}
                      className="inline-flex items-center gap-1 text-xs font-semibold text-blue-600 hover:text-blue-700"
                    >
                      Read Complete Article &rarr;
                    </Link>
                  </div>
                </div>
              </article>
            ))}
          </div>
        )}

        {/* Pagination */}
        {posts.last_page > 1 && (
          <div className="mt-12 flex items-center justify-center gap-2">
            {posts.prev_page_url && (
              <Link
                href={posts.prev_page_url}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
              >
                &larr; Previous
              </Link>
            )}
            <span className="px-4 text-xs font-medium text-slate-500">
              Page {posts.current_page} of {posts.last_page}
            </span>
            {posts.next_page_url && (
              <Link
                href={posts.next_page_url}
                className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:bg-slate-50"
              >
                Next &rarr;
              </Link>
            )}
          </div>
        )}
      </div>
    </StorefrontLayout>
  );
}
