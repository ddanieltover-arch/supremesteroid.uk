# MASTER PROMPT: Full-Stack SEO + GEO Website Optimization
### For AI Web Developer Agents (Cursor, Lovable, Bolt, v0, Windsurf, etc.)
> **Author:** Elvis Ndikum (El-Hub Ventures / EL-HUB VENTURES)
> **Version:** 2026 Edition — Covers Traditional SEO + Generative Engine Optimization (GEO/AEO)
> **Target Stack:** Next.js / React (adaptable to any framework)

---

## ⚡ AGENT MISSION STATEMENT

You are a senior full-stack SEO and GEO engineer. Your task is to perform a **complete, automated, production-grade optimization** of the website provided — from zero to fully indexed and AI-citation-ready. You will handle every layer: technical infrastructure, on-page content, structured data, GEO/AEO optimization, analytics, and link building intelligence. Leave nothing manual unless flagged explicitly.

**Target Website:** `[INSERT WEBSITE URL HERE]`
**Business Niche/Industry:** `[INSERT NICHE — e.g. SaaS, e-commerce, local restaurant, fintech]`
**Primary Geography:** `[INSERT TARGET COUNTRY/REGION — e.g. Nigeria, United States, Global]`
**Target Language:** `[INSERT LANGUAGE — e.g. English]`
**CMS / Stack:** `[INSERT STACK — e.g. Next.js 15, WordPress, Webflow, Shopify]`
**Seed Keywords (optional):** `[INSERT 3–10 SEED KEYWORDS OR LEAVE BLANK FOR AGENT TO RESEARCH]`

---

## SECTION 0 — PRE-FLIGHT AUDIT

Before any changes, perform an automated discovery scan across all dimensions below. Output a structured audit report.

### 0.1 Site Crawl & Inventory
- Crawl all pages, subpages, and URL patterns using sitemap discovery + link graph traversal
- Record: URL, HTTP status, title tag, meta description, H1, word count, canonical tag, indexability status, page type
- Flag: 4xx errors, 5xx errors, redirect chains (3+ hops), redirect loops, orphaned pages, non-canonical indexable duplicates
- Output: `audit_report.json` and `crawl_inventory.csv`

### 0.2 Core Web Vitals Snapshot
- Run Lighthouse audit on homepage, top 5 most-linked internal pages, and primary landing pages
- Record: LCP, INP (Interaction to Next Paint — replaces FID), CLS, TTFB, FCP, TBT
- Flag anything below: LCP > 2.5s, INP > 200ms, CLS > 0.1
- Output: `core_web_vitals_baseline.json`

### 0.3 Current SEO Baseline
- Check: Google Search Console connection, GA4 connection, sitemap submission status, robots.txt validity
- Record: current indexed pages, current organic keyword count (if GSC access available), domain authority estimate
- Output: `seo_baseline.json`

### 0.4 Competitor Identification
- Using the seed keywords or niche provided, identify top 5 organic competitors via SERP analysis
- For each competitor record: domain, estimated traffic, top 5 ranking keywords, domain authority
- If no seed keywords provided, auto-generate seed keywords from the homepage content and niche
- Output: `competitor_report.csv`

---

## SECTION 1 — KEYWORD RESEARCH & MAPPING

### 1.1 Automated Keyword Research
Run comprehensive keyword research using available APIs (SEMrush, Ahrefs, DataForSEO, or Google Keyword Planner). Generate:

- **Primary keywords** (high volume, commercial/transactional intent) — 10–20 keywords
- **Secondary keywords** (informational, supporting topics) — 20–50 keywords
- **Long-tail keywords** (conversational, question-based, high specificity) — 50–100 keywords
- **LSI/Semantic keywords** (entity-related, co-occurring terms AI engines associate with the topic)
- **Local intent keywords** (if geography is specified) — include city/region modifiers
- **Negative keywords** (irrelevant terms to suppress in content targeting)

For each keyword record: Search Volume, Keyword Difficulty, CPC, Intent (Informational/Navigational/Transactional/Local/Commercial), Current Rank (if applicable), SERP Feature present (Featured Snippet, People Also Ask, AI Overview, Local Pack).

### 1.2 Conversational & GEO Keyword Layer
Extract question-format and conversational queries (for GEO/AEO targeting):
- "What is [topic]?"
- "How do I [action related to niche]?"
- "Best [product/service] for [persona]?"
- "Compare [X] vs [Y]"
- "[Topic] near me / in [city]"
- Pull from "People Also Ask", "Related Searches", and Reddit/Quora forums

### 1.3 Keyword-to-Page Mapping
Map every keyword cluster to a specific page (existing or to be created):
- One primary keyword per page
- 3–5 secondary/LSI keywords per page
- Assign page type: Homepage, Service Page, Product Page, Blog/Article, FAQ, Landing Page, Location Page
- Flag keyword gaps where no existing page covers a high-value cluster → generate content brief

Output: `keyword_map.csv` with columns: Keyword | Volume | Difficulty | Intent | Assigned Page URL | Page Type | Gap (Y/N) | Priority (High/Medium/Low)

---

## SECTION 2 — TECHNICAL SEO IMPLEMENTATION

Implement all changes directly in the codebase. Do not just audit — fix everything.

### 2.1 Meta Tags — Automated Generation
For every page on the site:
- Generate unique `<title>` tags: Primary Keyword + Brand Name, 50–60 characters max
- Generate unique `<meta name="description">`: Include primary keyword, value proposition, CTA — 140–160 characters max
- Add `<meta name="robots" content="index, follow">` to all indexable pages
- Add `<meta name="robots" content="noindex, nofollow">` to: thank-you pages, checkout pages, search results pages, admin routes, duplicate content pages
- Set `<link rel="canonical">` on every page — always pointing to the definitive version

```html
<!-- Example: Next.js App Router generateMetadata() -->
export async function generateMetadata({ params }) {
  return {
    metadataBase: new URL('https://yourdomain.com'),
    title: {
      template: '%s | Brand Name',
      default: 'Primary Keyword | Brand Name',
    },
    description: 'Compelling 140-160 char description with keyword.',
    alternates: { canonical: '/current-slug' },
    openGraph: { ... },
    twitter: { ... },
  }
}
```

### 2.2 Open Graph + Social Meta
For every page:
```html
<meta property="og:title" content="..." />
<meta property="og:description" content="..." />
<meta property="og:image" content="https://domain.com/og-image.png" /> <!-- 1200x630px -->
<meta property="og:url" content="https://domain.com/page-slug" />
<meta property="og:type" content="website" /> <!-- or article, product -->
<meta property="og:site_name" content="Brand Name" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="..." />
<meta name="twitter:description" content="..." />
<meta name="twitter:image" content="..." />
```

### 2.3 XML Sitemap — Dynamic Generation
Generate and auto-maintain a dynamic XML sitemap:
- Include all indexable URLs: homepage, service pages, blog posts, product pages, category pages, location pages
- Exclude: admin, 404, checkout, search-result, noindex pages
- Set `<lastmod>`, `<changefreq>`, and `<priority>` for each URL
- Split into multiple sitemaps if > 50,000 URLs (sitemap index file)
- Place at `/sitemap.xml` and reference in `robots.txt`
- Auto-submit to Google Search Console and Bing Webmaster Tools via API after any content update

```ts
// Next.js: app/sitemap.ts
export default function sitemap() {
  return [
    { url: 'https://domain.com', lastModified: new Date(), priority: 1.0 },
    { url: 'https://domain.com/services', lastModified: new Date(), priority: 0.9 },
    // dynamically pull from CMS/DB
  ]
}
```

### 2.4 Robots.txt Configuration
```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /checkout/
Disallow: /search?
Disallow: /api/
Disallow: /*.json$
Sitemap: https://domain.com/sitemap.xml

User-agent: GPTBot
Allow: /

User-agent: ClaudeBot
Allow: /

User-agent: PerplexityBot
Allow: /
```
> Note: Explicitly allow AI crawlers (GPTBot, ClaudeBot, PerplexityBot, Googlebot-Extended) unless you have a specific reason not to — AI citation requires crawl access.

### 2.5 Core Web Vitals Optimization

**LCP (Largest Contentful Paint — target ≤ 2.5s):**
- Preload hero images: `<link rel="preload" href="/hero.webp" as="image">`
- Convert all images to WebP or AVIF format with fallbacks
- Use `next/image` with `priority` on above-fold images, `lazy` on all others
- Eliminate render-blocking CSS: inline critical CSS, defer the rest
- Set `fetchpriority="high"` on LCP image
- Deploy via CDN (Cloudflare, Vercel Edge, AWS CloudFront)
- Enable HTTP/2 or HTTP/3 on the server
- Use server-side rendering (SSR) or static generation (SSG/ISR) — avoid client-only rendering for key pages

**INP (Interaction to Next Paint — target ≤ 200ms):**
- Break up long JavaScript tasks using `scheduler.yield()` or `setTimeout(fn, 0)`
- Defer non-critical third-party scripts (analytics, chat widgets, ads) using `<Script strategy="lazyOnload">`
- Remove unused JavaScript — run bundle analysis and tree-shake aggressively
- Move heavy computations to Web Workers
- Avoid layout thrashing in event handlers

**CLS (Cumulative Layout Shift — target < 0.1):**
- Reserve space for all images and embeds with explicit `width` and `height` attributes
- Set `aspect-ratio` on image containers
- Preload fonts with `font-display: swap` or `optional`
- Use `next/font` to self-host fonts and eliminate FOUT
- Avoid inserting content above existing content without user interaction
- Reserve space for dynamic content (ads, banners) with min-height CSS

### 2.6 Mobile-First Optimization
- Ensure full content parity between mobile and desktop (Google indexes mobile-first)
- Use responsive CSS — no device-specific content hiding that strips indexable text
- Touch targets ≥ 48x48px
- No horizontal scrolling on viewports < 375px
- Test on real devices (iOS Safari, Android Chrome) — not just browser DevTools emulation
- Ensure font size ≥ 16px for body text on mobile to prevent iOS zoom on input focus
- Collapsible navigation must still expose all links in the DOM for crawler access

### 2.7 HTTPS & Security Headers
- Enforce HTTPS site-wide with 301 redirect from HTTP
- Set HSTS header: `Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`
- Add security headers for SEO trust signals:
  - `X-Content-Type-Options: nosniff`
  - `X-Frame-Options: DENY`
  - `Referrer-Policy: strict-origin-when-cross-origin`
  - `Content-Security-Policy` (customized to stack)
- Ensure all resources (images, scripts, fonts) load over HTTPS (fix mixed content)

### 2.8 URL Architecture
- All URLs: lowercase, hyphen-separated, no underscores, no trailing slashes (or enforce consistently)
- Maximum URL depth: 3 levels from root (e.g. `/blog/category/post-title`)
- Remove: parameters like `?ref=`, `?utm_source=` from canonical — these should only appear in tracking, not as canonical URLs
- Implement 301 redirects for any renamed/moved pages — never leave 404s where content previously existed
- Avoid duplicate content from URL variants: `www` vs non-www, `/page/` vs `/page`, `http` vs `https` — pick one, canonicalize all others

### 2.9 Crawl Budget Optimization
- Block faceted navigation duplicates (e.g. filter URLs with `?color=red&size=M`) from indexing
- Paginated content: use `rel="next"` / `rel="prev"` (Bing) and canonical pointing to page 1 for thin paginated pages, or consolidate with `?page=all` if content volume allows
- Consolidate thin content pages (< 300 words) into hub pages rather than leaving them indexed
- Run a crawl depth report — any valuable page more than 4 clicks from the homepage should be surfaced via navigation or internal linking

---

## SECTION 3 — ON-PAGE SEO IMPLEMENTATION

### 3.1 Heading Structure (H1–H6)
For every page:
- Exactly **one H1** — contains the primary keyword, near top of page, unique per page
- H2s for major section breaks — include secondary keywords naturally
- H3s for sub-sections under H2s
- No heading tags used purely for styling (use CSS classes instead)
- Headings must follow a logical hierarchy — never skip from H1 to H4

### 3.2 Content Quality Standards
- Minimum 800 words for service/product pages; 1,500+ for blog/pillar content
- Keyword density: 1–2% for primary keyword — do not stuff
- Include LSI and semantic variants throughout naturally
- Use short paragraphs (2–4 sentences max), bullet points for lists, numbered lists for steps
- Answer the primary search query explicitly within the first 100 words of body content
- Add "Answer Capsule" — a 40–60 word direct answer to the main query, formatted as a bolded intro paragraph (boosts Featured Snippet and AI citation eligibility)
- Include relevant statistics with citations to authoritative sources (boosts E-E-A-T and AI citation probability)

### 3.3 Image Optimization
- Compress all images: WebP/AVIF format, quality 80–85%
- Descriptive, keyword-inclusive `alt` text for every image — especially images that convey information
- Descriptive `alt` for decorative images: `alt=""` (empty, not missing)
- File names: descriptive slugs (e.g. `seo-checklist-2026.webp` not `IMG_1234.webp`)
- Add `<figure>` and `<figcaption>` for key informational images — helps AI extract context
- Implement responsive images with `srcset` and `sizes` attributes

### 3.4 Internal Linking Architecture
Build a pillar–cluster internal linking model:
- Each **Pillar Page** (broad topic hub) links to all related **Cluster Pages**
- Each **Cluster Page** links back to its Pillar Page and to sibling clusters where relevant
- Homepage links to all primary Pillar Pages
- Use descriptive, keyword-rich anchor text (not "click here" or "read more")
- Minimum 3–5 internal links per page
- Fix all broken internal links
- Run PageRank flow analysis — ensure high-authority pages pass link equity to conversion pages

Output: `internal_linking_plan.csv` → From Page | To Page | Anchor Text | Priority

### 3.5 Content Freshness Signals
- Add `<meta name="revised">` or visible "Last Updated: [Date]" for evergreen pages
- Implement `dateModified` in Article schema (see Section 4)
- Set up automated content refresh reminders for top-performing pages every 6 months
- Include current year in time-sensitive titles where appropriate

---

## SECTION 4 — STRUCTURED DATA (SCHEMA MARKUP) — FULL IMPLEMENTATION

Implement JSON-LD structured data for every applicable page type. Inject via `<Script type="application/ld+json">` in the `<head>`. Validate every schema with Google's Rich Results Test before deployment.

### 4.1 Global Schemas (every page)

**Organization Schema:**
```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "name": "Brand Name",
  "url": "https://domain.com",
  "logo": {
    "@type": "ImageObject",
    "url": "https://domain.com/logo.png",
    "width": 300,
    "height": 60
  },
  "sameAs": [
    "https://twitter.com/handle",
    "https://linkedin.com/company/handle",
    "https://facebook.com/handle",
    "https://en.wikipedia.org/wiki/Brand" // if exists
  ],
  "contactPoint": {
    "@type": "ContactPoint",
    "telephone": "+1-XXX-XXX-XXXX",
    "contactType": "Customer Support"
  }
}
```

**WebSite + Sitelinks SearchBox:**
```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "url": "https://domain.com",
  "name": "Brand Name",
  "potentialAction": {
    "@type": "SearchAction",
    "target": "https://domain.com/search?q={search_term_string}",
    "query-input": "required name=search_term_string"
  }
}
```

**BreadcrumbList (every page except homepage):**
```json
{
  "@context": "https://schema.org",
  "@type": "BreadcrumbList",
  "itemListElement": [
    { "@type": "ListItem", "position": 1, "name": "Home", "item": "https://domain.com" },
    { "@type": "ListItem", "position": 2, "name": "Category", "item": "https://domain.com/category" },
    { "@type": "ListItem", "position": 3, "name": "Current Page", "item": "https://domain.com/category/page" }
  ]
}
```

### 4.2 Page-Type Specific Schemas

**Article / BlogPosting (all blog posts):**
```json
{
  "@context": "https://schema.org",
  "@type": "BlogPosting",
  "headline": "Post Title",
  "description": "Post meta description",
  "image": "https://domain.com/post-image.webp",
  "author": {
    "@type": "Person",
    "name": "Author Name",
    "url": "https://domain.com/author/author-name"
  },
  "publisher": { "@type": "Organization", "name": "Brand", "logo": {...} },
  "datePublished": "2026-01-01",
  "dateModified": "2026-04-01",
  "mainEntityOfPage": { "@type": "WebPage", "@id": "https://domain.com/blog/post-slug" }
}
```

**FAQPage (FAQ sections and FAQ pages):**
```json
{
  "@context": "https://schema.org",
  "@type": "FAQPage",
  "mainEntity": [
    {
      "@type": "Question",
      "name": "Question text here?",
      "acceptedAnswer": {
        "@type": "Answer",
        "text": "Answer text here. Keep answers under 300 characters for snippet eligibility."
      }
    }
  ]
}
```

**LocalBusiness (if location-based):**
```json
{
  "@context": "https://schema.org",
  "@type": "LocalBusiness",
  "name": "Business Name",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "123 Main St",
    "addressLocality": "City",
    "addressRegion": "State",
    "postalCode": "00000",
    "addressCountry": "US"
  },
  "geo": { "@type": "GeoCoordinates", "latitude": 0.0, "longitude": 0.0 },
  "telephone": "+1-XXX-XXX-XXXX",
  "openingHoursSpecification": [...],
  "priceRange": "$$",
  "url": "https://domain.com",
  "sameAs": ["https://maps.google.com/..."]
}
```

**Product (e-commerce):**
```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "name": "Product Name",
  "image": ["https://domain.com/product.webp"],
  "description": "Product description",
  "sku": "SKU123",
  "brand": { "@type": "Brand", "name": "Brand Name" },
  "offers": {
    "@type": "Offer",
    "priceCurrency": "USD",
    "price": "99.00",
    "availability": "https://schema.org/InStock",
    "url": "https://domain.com/product/slug"
  },
  "aggregateRating": {
    "@type": "AggregateRating",
    "ratingValue": "4.8",
    "reviewCount": "127"
  }
}
```

**HowTo (tutorial and guide pages):**
```json
{
  "@context": "https://schema.org",
  "@type": "HowTo",
  "name": "How to [Task]",
  "description": "Step-by-step guide to [task]",
  "step": [
    { "@type": "HowToStep", "name": "Step 1 title", "text": "Step 1 instructions" },
    { "@type": "HowToStep", "name": "Step 2 title", "text": "Step 2 instructions" }
  ]
}
```

**SoftwareApplication (SaaS/App products):**
```json
{
  "@context": "https://schema.org",
  "@type": "SoftwareApplication",
  "name": "App Name",
  "applicationCategory": "BusinessApplication",
  "operatingSystem": "Web, iOS, Android",
  "offers": { "@type": "Offer", "price": "0", "priceCurrency": "USD" },
  "aggregateRating": { "@type": "AggregateRating", "ratingValue": "4.9", "reviewCount": "500" }
}
```

**Person (founder/author pages):**
```json
{
  "@context": "https://schema.org",
  "@type": "Person",
  "name": "Full Name",
  "url": "https://domain.com/about",
  "jobTitle": "Founder & CEO",
  "worksFor": { "@type": "Organization", "name": "Brand Name" },
  "sameAs": ["https://linkedin.com/in/handle", "https://twitter.com/handle"]
}
```

**VideoObject (video content pages):**
```json
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": "Video Title",
  "description": "Video description",
  "thumbnailUrl": "https://domain.com/thumbnail.webp",
  "uploadDate": "2026-01-01",
  "duration": "PT4M30S",
  "embedUrl": "https://www.youtube.com/embed/VIDEO_ID",
  "contentUrl": "https://domain.com/video.mp4"
}
```

---

## SECTION 5 — GEO / AEO OPTIMIZATION (Generative Engine Optimization)

Optimize for AI search engines: ChatGPT, Google AI Overviews, Perplexity, Bing Copilot, Claude, and Gemini. These engines synthesize content rather than returning blue links — you must become the source they cite.

### 5.1 Answer Capsule Architecture
For every primary page and blog post, add a clearly marked "Answer Block" within the first 200 words:
- Write a 40–60 word direct answer to the primary query the page targets
- Format as a distinct `<div>` or `<section>` with an `id="answer"` attribute
- Use clear, factual, declarative language — no marketing fluff
- Follow with supporting evidence: statistics, lists, definitions, comparisons

```html
<section id="answer" aria-label="Quick Answer">
  <p><strong>Quick Answer:</strong> [40–60 word direct answer to the main query, using natural language, citing a specific fact or definition where relevant.]</p>
</section>
```

### 5.2 Modular, Question-First Content Structure
Restructure all key pages so that:
- Every H2 and H3 is phrased as a question or direct topic label humans would ask an AI
- Each section answers one question in 75–300 words before moving to the next
- Include explicit "Definition" blocks for key terms (entity recognition)
- Use `<dl>`, `<dt>`, `<dd>` markup for definition lists
- Add comparison tables (`<table>`) for "X vs Y" queries
- Add ordered lists (`<ol>`) for "how to" steps

### 5.3 E-E-A-T Signals (Experience, Expertise, Authoritativeness, Trustworthiness)
- Add author bio with credentials, photo, and LinkedIn/social link on all content pages
- Display "Last Updated" date visibly on evergreen content
- Include original research, original data, original quotes — AI engines prioritize primary sources
- Cite external authoritative sources with live links (link to .gov, .edu, industry studies)
- Add a Trust page: About, Team, Certifications, Press mentions, Awards
- Earn and display customer reviews/testimonials with Review schema
- List all credentials, licenses, certifications relevant to your niche

### 5.4 Entity Optimization
- Establish one primary entity per page (the "main thing" this page is about)
- Link entity names to Wikipedia where possible (external link for recognition)
- Bind entities to Wikidata — create/update Wikidata entry for your brand if it doesn't exist
- Include entity synonyms and alternative names in copy naturally
- Internal link structure should reinforce entity relationships: blog posts about "email marketing" linking to the email marketing service page
- Use Google's Knowledge Graph to check if your brand entity exists; if not, create structured pathways to build it (Wikipedia, Wikidata, Google Business Profile, consistent NAP citations)

### 5.5 Semantic HTML for AI Parsability
Use proper semantic HTML so AI crawlers can parse structure without CSS:
```html
<header> — site header and navigation
<main> — primary content
<article> — standalone content (blog post, news)
<section> — major content blocks within a page
<aside> — supplementary content
<footer> — footer
<nav> — navigation menus
<figure> + <figcaption> — images with context
<table> — structured comparative data
<dl><dt><dd> — term definitions
```
Add `id` attributes to major sections for fragment linking (e.g. `id="what-is-geo"`, `id="steps"`, `id="faq"`).

### 5.6 AI Crawler Access
Ensure all AI bots can crawl and index content:
- Allow GPTBot, ClaudeBot, PerplexityBot, GoogleBot-Extended in robots.txt (already set in Section 2.4)
- No JavaScript-only rendering for key content — AI crawlers don't execute JS reliably
- All critical content must be in the HTML source (SSR or SSG)
- Use `llms.txt` file at root (`/llms.txt`) — a plain-text overview of the site for LLM crawlers:

```
# [Brand Name]
> [One-sentence brand description]

[Brand Name] is a [industry] company helping [target persona] achieve [value proposition].

## Key Pages
- /: Homepage — overview of all services
- /services: Full services listing
- /blog: Knowledge hub for [niche] topics
- /about: Company information, team, and mission

## Primary Topics
[List 5–10 core topics this site covers]
```

### 5.7 Unlinked Brand Mention Strategy
- Set up Google Alerts and Brand24/Mention.com monitoring for your brand name
- When brand is mentioned without a link, reach out to request a link (conversion of unlinked mentions to linked citations)
- Unlinked brand mentions carry strong AI citation signals — prioritize volume of quality mentions across trusted publications

### 5.8 GEO Content Expansion
For each high-priority GEO keyword cluster, create dedicated content:
- **Glossary Page:** Define all key terms in your niche with 100–200 word definitions — AI engines pull heavily from glossaries
- **FAQ Hub Page:** Compile all "People Also Ask" and conversational questions into one structured FAQ page with FAQPage schema
- **Comparison Pages:** Create "[Brand] vs [Competitor]" and "[Option A] vs [Option B]" pages — these score high in AI-generated recommendation responses
- **Statistics Page:** Aggregate authoritative industry statistics with citations — highly linkable and frequently cited by AI
- **Definitive Guide Pages:** Long-form, 3,000–6,000 word pillar content on core topics — these become AI's preferred citation source

---

## SECTION 6 — LOCAL SEO (Include if geography is specific)

### 6.1 Google Business Profile
- Create or claim and fully optimize Google Business Profile (GBP)
- Complete all fields: Name, Address, Phone (NAP), Hours, Website, Services, Photos (10+ high-quality), Products, Description (750 chars with keywords)
- Select the most accurate primary and secondary categories
- Add booking link / appointment URL if applicable
- Enable messaging
- Post weekly GBP updates (Events, Offers, What's New) — treated as fresh content signals

### 6.2 NAP Consistency
- Ensure Name, Address, Phone number is identical across: website, GBP, all citation directories, social profiles
- Audit and fix inconsistencies across: Yelp, Yellow Pages, Bing Places, Apple Maps, Facebook, TripAdvisor, industry directories
- Use a citation management tool (BrightLocal, Yext, or Moz Local) to push consistent NAP to 50+ directories

### 6.3 Local Schema
Implement LocalBusiness schema (see Section 4.2) with:
- `geo` coordinates
- `areaServed` field listing all service areas
- `openingHoursSpecification`
- `hasMap` with Google Maps URL

### 6.4 Location Pages (Multi-location)
If serving multiple cities/regions, create individual location pages:
- Unique, non-duplicate content for each location (not just name-swapped templates)
- Include: local address, local phone, local team photos, local testimonials, local FAQ
- Embed Google Map for each location
- LocalBusiness schema per location page
- Internal link all location pages from a main "Locations" hub page

### 6.5 Review Strategy
- Send post-purchase/post-service automated review request emails with direct link to GBP review form
- Respond to every review (positive and negative) within 48 hours
- Display top reviews on website with Review/AggregateRating schema

---

## SECTION 7 — CONTENT STRATEGY & CREATION PIPELINE

### 7.1 Content Audit (Existing Content)
- Categorize all existing pages: Keep as-is | Improve | Consolidate | Redirect | Delete
- Flag: thin content (< 300 words), duplicate content, outdated content, keyword cannibalisation (multiple pages targeting same keyword)
- Merge duplicate/thin pages with 301 redirects to consolidated, comprehensive versions

### 7.2 Content Calendar (Automated Generation)
Generate a 90-day content calendar based on keyword gaps identified in Section 1.3:
- Format: Publish Date | Content Title | Content Type | Primary Keyword | Intent | Word Count Target | Status
- Prioritise: High-volume + Low-difficulty keywords first
- Mix: 60% informational (blog/guides), 20% commercial (service/product pages), 20% navigational (brand/comparison)
- Schedule at minimum 2 pieces of content per week

### 7.3 Content Brief Template (generate for each planned piece)
For each content piece in the calendar:
```
TITLE: [Proposed H1 title with primary keyword]
PRIMARY KEYWORD: [keyword | volume | difficulty]
SECONDARY KEYWORDS: [3-5 LSI keywords]
CONTENT TYPE: [Blog Post | Pillar Page | Landing Page | FAQ | Comparison]
INTENT: [Informational | Commercial | Transactional]
TARGET WORD COUNT: [XXX words]
ANSWER CAPSULE: [40–60 word direct answer to write first]
SUGGESTED H2s:
  - H2: [Question-format heading]
  - H2: [Question-format heading]
  - H2: FAQs about [topic]
KEY POINTS TO COVER: [Bulleted list of required content elements]
SCHEMA TO IMPLEMENT: [Article | FAQ | HowTo | etc.]
INTERNAL LINKS TO INCLUDE:
  - Link to [Pillar Page URL] with anchor "[anchor text]"
EXTERNAL AUTHORITATIVE SOURCES TO CITE:
  - [URL 1], [URL 2]
CTA: [Primary call to action for this page]
```

---

## SECTION 8 — ANALYTICS & TRACKING SETUP

### 8.1 Google Analytics 4 (GA4)
- Install GA4 with Google Tag Manager (GTM)
- Configure data stream for web
- Set up conversion events:
  - `generate_lead` — form submissions
  - `purchase` — e-commerce transactions
  - `begin_checkout` — checkout initiation
  - `sign_up` — account registration
  - `schedule_appointment` — booking completions
  - `click` on CTAs, phone numbers, email links
- Enable enhanced measurement: scroll depth, outbound clicks, file downloads, video engagement, site search
- Connect GA4 to Google Search Console
- Enable BigQuery export for raw data retention (GA4 Free tier: 1M events/day)

### 8.2 Google Search Console
- Verify domain via DNS TXT record
- Submit XML sitemap
- Set preferred domain (www vs non-www)
- Monitor weekly: Coverage issues, Core Web Vitals, Manual Actions, Mobile Usability, Rich Results eligibility
- Set up email alerts for critical issues
- Use Performance report to track: Impressions, Clicks, CTR, Average Position per query and page

### 8.3 KPI Dashboard
Set up automated reporting dashboard (Google Looker Studio or equivalent) tracking:

| KPI | Baseline | Target | Data Source | Review Cadence |
|-----|----------|--------|-------------|---------------|
| Organic Sessions | — | +30% in 90 days | GA4 | Weekly |
| Organic Keywords (top 10) | — | 2x in 6 months | GSC / Ahrefs | Monthly |
| Average Position | — | < 15 overall | GSC | Monthly |
| CTR | — | > 3% | GSC | Monthly |
| LCP | — | ≤ 2.5s | GSC CWV | Weekly |
| INP | — | ≤ 200ms | GSC CWV | Weekly |
| CLS | — | < 0.1 | GSC CWV | Weekly |
| Domain Authority | — | +5 points in 6 months | Ahrefs/Moz | Monthly |
| Referring Domains | — | +10/month | Ahrefs | Monthly |
| AI Citations | — | Track mentions in ChatGPT / Perplexity / Gemini | Brand monitoring | Monthly |
| Conversion Rate | — | Site-specific target | GA4 | Weekly |

### 8.4 Rank Tracking
Set up automated rank tracking for top 50 target keywords:
- Tools: Ahrefs, SEMrush, SERPWatcher, or DataForSEO API
- Track: Desktop and Mobile rankings separately
- Track: Featured Snippet wins, People Also Ask inclusions, AI Overview appearances
- Alert: Any ranking drop > 5 positions on a priority keyword

### 8.5 AI Visibility Monitoring
Track appearance in AI search engines:
- Test brand and primary keyword queries weekly in: ChatGPT, Perplexity, Google AI Overviews, Bing Copilot, Claude
- Record: Citation rate, mention quality, brand position in AI responses
- Tools: Profound.com, Brandwatch, or manual spot-checking protocol
- KPI: AI Citation Rate = (Queries where brand appears / Total tested queries) × 100

---

## SECTION 9 — LINK BUILDING & DIGITAL PR

### 9.1 Backlink Audit
- Crawl the existing backlink profile using Ahrefs or Majestic
- Identify and disavow toxic backlinks (spam score > 60, irrelevant foreign directories, PBNs)
- Submit disavow file to Google Search Console
- Identify the top 20 highest-value existing backlinks — find patterns to replicate

### 9.2 Competitor Backlink Gap Analysis
For each of the 5 competitors identified in Section 0.4:
- Pull their top 50 referring domains
- Find domains linking to 3+ competitors but NOT to our site (link gap opportunities)
- Prioritize: Domain Rating > 40, topically relevant, real editorial content
- Output: `link_gap_opportunities.csv` → Domain | DR | Linking to Competitors | Opportunity Type

### 9.3 Link Building Campaign — Target Prioritization
Execute the following link-building tactics in order of ROI:

**Tier 1 — Unlinked Brand Mentions (Quick Wins)**
- Monitor brand mentions using Google Alerts, Mention.com, Ahrefs Alerts
- Reach out to mention authors requesting a hyperlink to the brand
- Template:
  > Subject: Quick note about your recent mention of [Brand]
  > Hi [Name], I noticed you mentioned [Brand] in your article "[Article Title]". Thank you! Would you consider adding a link to [URL]? It would help your readers find us directly. Happy to return the favour if I can help with your content.

**Tier 2 — Digital PR & Data-Driven Content**
- Create one original data study, survey, or research report per quarter on a topic relevant to the niche
- Use AI to analyze publicly available data and produce unique industry insights
- Write a compelling 5-bullet press release with data-driven hook
- Pitch to: industry publications, news sites, journalists covering the niche
- Pitch template:
  > Subject: [Statistic]: New [Niche] research from [Brand]
  > Hi [Journalist name],
  > We've just published new research showing [compelling stat] among [audience]. [One-sentence why this matters to their readers].
  > Key findings:
  > • [Finding 1 with number]
  > • [Finding 2 with number]
  > • [Finding 3 with number]
  > Full study: [URL]. Happy to provide data tables or arrange an expert quote. Would you cover this?
  > [Name]

**Tier 3 — Guest Posting on Authority Sites**
- Identify: DR 40+ sites in niche accepting guest contributions
- Research editor names via LinkedIn, Twitter/X, or site masthead
- Pitch a specific, original, data-backed topic (not a generic "I want to write for you")
- Deliver content that exceeds their average word count and quality
- Include one contextual link to a high-value page on the target website
- Track in `guest_post_pipeline.csv`: Target Site | DR | Editor Contact | Pitch Status | Published Link

**Tier 4 — Broken Link Building**
- Use Ahrefs "Broken Backlinks" report on competitor domains
- Find: high-DR pages linking to dead competitor content
- Create better replacement content on your site if the topic is relevant
- Reach out to the linking site informing them of the dead link and suggesting your content as a replacement

**Tier 5 — Resource Page & Listicle Placements**
- Search: `[niche] + "best resources"`, `[niche] + "useful tools"`, `[niche] + "recommended"` — site:edu, site:gov or high-DR domains
- Pitch inclusion of your tool, content, or product where genuinely relevant and valuable

**Tier 6 — HARO / Source of Sources Platforms**
- Sign up for: Featured.com, Qwoted, Help a B2B Writer, Connectively (formerly HARO)
- Respond daily to journalist queries in your niche with expert quotes (under 150 words)
- Formula for HARO pitches: Hook Sentence → Credentials → Unique Insight → Data point → Bio
- Target: 5–10 pitches per week → expect 1–3 placements per month from quality pitching

**Tier 7 — Podcast & Newsletter Appearances**
- Identify top 20 podcasts in your niche (by download count or audience relevance)
- Pitch as a guest expert — provide a media kit and specific topic angle
- Every podcast appearance generates a dofollow citation from the show notes
- Target newsletters with 5,000+ subscribers — offer free content, tools, or exclusive data

### 9.4 Automated Outreach Infrastructure
Set up automated outreach pipeline using:
- **Prospecting:** Ahrefs, SEMrush, Hunter.io, Apollo.io for contact finding
- **Outreach automation:** Instantly.ai, Smartlead, Woodpecker, or Pitchbox
- **CRM tracking:** Track all outreach in `link_building_crm.csv`: Prospect Domain | DR | Contact Name | Email | Outreach Date | Follow-up 1 Date | Follow-up 2 Date | Status | Link Acquired (Y/N) | Link URL
- **A/B testing:** Test 2 subject line variants and 2 email body variants per campaign — optimize on open rate and reply rate
- **Follow-up sequence:** Initial pitch → Follow-up at Day 3 → Final follow-up at Day 7 → Archive if no reply
- **Monthly volume target:** 50+ outreach contacts per month → targeting 5–10 link acquisitions per month

### 9.5 Link Velocity & Quality Standards
Only count a link as a win if it meets all of:
- Domain Rating ≥ 30
- Real, human-readable editorial content (no directory spam, no PBNs)
- Topically relevant to the site's niche
- Link is dofollow OR a high-authority nofollow (nofollow from Forbes/NYT etc. still carries brand signal)
- Referring domain has real organic traffic (check Ahrefs traffic estimate > 100/month)

---

## SECTION 10 — MULTILINGUAL & INTERNATIONAL SEO (Include if targeting multiple regions)

- Implement `hreflang` tags for each language/region variant
- Use separate URLs for each language (subdirectory preferred: `/en/`, `/fr/`) — not query parameters
- Translate metadata and structured data — not just body content
- Localize content (dates, currencies, measurements, cultural references)
- Create region-specific XML sitemaps
- Use international targeting settings in Google Search Console

---

## SECTION 11 — AUTOMATION & CI/CD INTEGRATION

### 11.1 Pre-Deployment SEO Checks (CI Pipeline)
Add automated SEO validation to the CI/CD pipeline (GitHub Actions / GitLab CI):

```yaml
# .github/workflows/seo-check.yml
name: SEO Validation
on: [pull_request]
jobs:
  seo:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run Lighthouse CI
        uses: treosh/lighthouse-ci-action@v10
        with:
          urls: |
            https://staging.domain.com/
            https://staging.domain.com/blog/
          budgetPath: .lighthouserc.json
          uploadArtifacts: true
      - name: Check for missing meta tags
        run: node scripts/seo-audit.js
      - name: Validate structured data
        run: node scripts/validate-schema.js
      - name: Check for broken internal links
        run: npx broken-link-checker https://staging.domain.com --recursive
```

### 11.2 Automated Sitemap Resubmission
After every content deployment, trigger sitemap resubmission:
```bash
# Auto-ping Google Search Console on deploy
curl "https://www.google.com/ping?sitemap=https://domain.com/sitemap.xml"
curl "https://www.bing.com/ping?sitemap=https://domain.com/sitemap.xml"
```

### 11.3 Performance Monitoring
- Set up Real User Monitoring (RUM) with Vercel Analytics, Cloudflare RUM, or Sentry
- Alert on: any Core Web Vitals regression, any new 404 errors, any crawl errors in GSC
- Weekly automated Lighthouse audit on production URLs
- Monthly automated SEO crawl with broken link detection

### 11.4 Content Freshness Automation
- Set up a cron job to flag pages not updated in > 6 months for content review
- Auto-update `dateModified` in Article schema on any content edit
- Auto-ping sitemap on any page update

---

## SECTION 12 — DELIVERABLES CHECKLIST

Upon completion, provide all of the following:

### Files to Deliver
- [ ] `audit_report.json` — Full pre-optimization audit findings
- [ ] `crawl_inventory.csv` — All URLs, status codes, SEO elements
- [ ] `core_web_vitals_baseline.json` — Before/after CWV scores
- [ ] `keyword_map.csv` — Full keyword research and page mapping
- [ ] `competitor_report.csv` — Competitor analysis
- [ ] `seo_strategy.md` — Full SEO implementation plan
- [ ] `internal_linking_plan.csv` — Pillar-cluster internal link map
- [ ] `content_calendar.csv` — 90-day content calendar
- [ ] `content_briefs/` — Individual briefs for each planned content piece
- [ ] `schema_validation_report.json` — Rich Results Test results for all schemas
- [ ] `link_gap_opportunities.csv` — Competitor backlink gaps
- [ ] `link_building_crm.csv` — Outreach tracker (seeded with first 50 prospects)
- [ ] `guest_post_pipeline.csv` — Guest posting targets
- [ ] `email_templates.md` — All outreach email templates
- [ ] `analytics_setup.md` — GA4 + GSC configuration documentation
- [ ] `kpis_dashboard.md` — KPI definitions, baseline, targets, data sources
- [ ] `geo_content_briefs.md` — GEO-specific content expansion plan
- [ ] `llms.txt` — AI crawler index file (placed at site root)
- [ ] `disavow.txt` — Toxic backlinks disavow file (if applicable)
- [ ] `robots.txt` — Final configured robots file
- [ ] `sitemap.xml` — Final validated sitemap

### Validation Tests to Pass
- [ ] Google Rich Results Test — 0 errors on all schema implementations
- [ ] Google Mobile-Friendly Test — Pass on all primary pages
- [ ] PageSpeed Insights — LCP ≤ 2.5s, INP ≤ 200ms, CLS < 0.1 on all primary pages
- [ ] Screaming Frog crawl — 0 broken internal links, 0 missing meta tags, 0 duplicate titles/descriptions
- [ ] Google Search Console — Sitemap indexed, 0 critical coverage errors
- [ ] W3C HTML Validator — 0 critical HTML errors on primary templates
- [ ] SSL Labs — A or A+ HTTPS rating
- [ ] robots.txt Tester in GSC — All desired pages accessible, all blocked pages confirmed blocked

---

## AGENT EXECUTION ORDER

Execute sections in this strict sequence to avoid rework:

```
Phase 1 (Days 1–3):   Section 0 → Full Audit
Phase 2 (Days 3–5):   Section 1 → Keyword Research & Mapping
Phase 3 (Days 5–10):  Section 2 → Technical SEO Implementation
Phase 4 (Days 8–12):  Section 3 → On-Page SEO
Phase 5 (Days 10–14): Section 4 → Structured Data (all schemas)
Phase 6 (Days 12–16): Section 5 → GEO/AEO Optimization
Phase 7 (Days 14–18): Section 6 → Local SEO (if applicable)
Phase 8 (Days 16–20): Section 7 → Content Strategy & Calendar
Phase 9 (Days 18–21): Section 8 → Analytics & Tracking Setup
Phase 10 (Days 20+):  Section 9 → Link Building (ongoing)
Phase 11 (Continuous): Section 11 → Automation & CI/CD
```

---

## CONSTRAINTS & QUALITY STANDARDS

- **Never use black-hat techniques:** No keyword stuffing, no hidden text, no cloaking, no PBN links, no link buying, no duplicate content farming
- **Never break existing functionality** when implementing SEO changes — run in staging first
- **E-E-A-T above all:** If in doubt, ask what a real human expert would write — write that
- **Mobile-first always:** Test every change on a real mobile device before pushing to production
- **AI-first content structure:** Every new page should pass the test: "Would ChatGPT cite this as a source?"
- **Cite your sources:** Any statistic or claim in content must link to its source
- **Speed budget:** No page should exceed 3MB total page weight. No single JavaScript bundle > 200KB gzipped
- **Accessibility:** WCAG 2.1 AA minimum — good accessibility correlates with good SEO

---

*This prompt was engineered by Elvis Ndikum (UBIRT.AI / El-Hub Ventures) for use with AI web developer agents. It represents a complete, production-grade SEO + GEO optimization blueprint for any professional website.*
