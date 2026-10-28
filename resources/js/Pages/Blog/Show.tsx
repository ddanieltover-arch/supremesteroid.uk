import { Link } from '@inertiajs/react';
import { BookOpen, Calendar, Clock, Link2 } from 'lucide-react';
import { useState } from 'react';
import { InfoPageChrome } from '@/components/content/InfoPageChrome';
import StorefrontLayout from '../../Layouts/StorefrontLayout';
import type { SeoProps } from '@/types';

type BlogPostDetail = {
  id: string;
  title: string;
  slug: string;
  content: string;
  excerpt?: string | null;
  featured_image_url?: string | null;
  published_at: string;
  reading_time: string;
  word_count: number;
  author: string;
};

type RecentPost = {
  id: string;
  title: string;
  slug: string;
  excerpt: string;
  featured_image_url?: string | null;
  published_at: string;
};

export default function BlogShow({
  post,
  recent_posts = [],
  seo,
}: {
  post: BlogPostDetail;
  recent_posts: RecentPost[];
  seo?: SeoProps;
}) {
  const [copied, setCopied] = useState(false);

  const handleShare = async () => {
    const url = window.location.href;
    try {
      if (navigator.share) {
        await navigator.share({ title: post.title, url });
        return;
      }
      await navigator.clipboard.writeText(url);
      setCopied(true);
      window.setTimeout(() => setCopied(false), 2000);
    } catch {
      // User cancelled share sheet or clipboard unavailable.
    }
  };

  return (
    <StorefrontLayout seo={seo}>
      <InfoPageChrome
        title={post.title}
        breadcrumbParent="Articles"
        breadcrumbParentHref="/blog"
        badgeLabel="Article"
        badgeIcon={BookOpen}
        subtitle={post.excerpt || 'Educational guides and reference articles from the Supreme Steroids UK storefront.'}
        activeHref="/blog"
        backHref="/blog"
        backLabel="Back to Articles"
        heroMeta={
          <div className="mt-6 flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-white/10 pt-5 text-xs text-slate-300">
            <span className="inline-flex items-center gap-1.5">
              <Calendar className="h-3.5 w-3.5 text-slate-400" aria-hidden />
              <time dateTime={post.published_at}>{post.published_at}</time>
            </span>
            <span className="inline-flex items-center gap-1.5">
              <Clock className="h-3.5 w-3.5 text-slate-400" aria-hidden />
              {post.reading_time}
            </span>
            <span className="text-slate-400">{post.author}</span>
            <button
              type="button"
              onClick={handleShare}
              className="inline-flex items-center gap-1.5 rounded-lg px-2 py-1 font-semibold text-slate-200 transition hover:bg-white/10 hover:text-white"
            >
              <Link2 className="h-3.5 w-3.5" aria-hidden />
              {copied ? 'Link copied' : 'Copy link'}
            </button>
          </div>
        }
        sidebarExtra={
          recent_posts.length > 0 ? (
            <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
              <h3 className="text-xs font-bold uppercase tracking-wider text-slate-400">Recent Articles</h3>
              <ul className="mt-3 space-y-4">
                {recent_posts.slice(0, 3).map((related) => (
                  <li key={related.id}>
                    <Link href={`/blog/${related.slug}`} className="group block">
                      <span className="text-[11px] text-slate-400">{related.published_at}</span>
                      <span className="mt-1 block text-sm font-semibold leading-snug text-slate-900 transition-colors group-hover:text-blue-600">
                        {related.title}
                      </span>
                    </Link>
                  </li>
                ))}
              </ul>
            </div>
          ) : null
        }
      >
        {post.featured_image_url ? (
          <figure className="mb-8 overflow-hidden rounded-xl border border-slate-200 bg-slate-100">
            <img src={post.featured_image_url} alt="" className="aspect-[21/9] w-full object-cover" />
          </figure>
        ) : null}
        <article className="blog-prose max-w-none" dangerouslySetInnerHTML={{ __html: post.content }} />
      </InfoPageChrome>
    </StorefrontLayout>
  );
}
