# SEO + GEO strategy — Supreme Steroids UK

## Phase 1 (this delivery)

Technical and AI-crawler foundations on Laravel + Inertia:

1. Code-evidence audit artifacts (`docs/seo/`)
2. Dynamic `/sitemap.xml` for indexable storefront URLs only
3. `/robots.txt` with private-route disallow + AI bot allow + Sitemap line
4. 301 `/catalogue` → `/shop`
5. SeoHead: `og:url`, `og:site_name`, `og:locale`
6. Richer Product `Offer` fields; BlogPosting; homepage FAQPage
7. `/llms.txt` for LLM crawlers (compliant, non-medical wording)
8. Feature tests for sitemap, robots, redirect, schema smoke

## Phase 2 (this delivery — GA4/GTM skipped)

1. Server-rendered SEO bootstrap in Blade (`resources/views/partials/seo-bootstrap.blade.php`) so crawlers see title/meta/OG/JSON-LD without Inertia SSR
2. Faceted/paginated/sorted catalogue URLs → `noindex,follow` (clean `/shop`, `/category/*`, `/brand/*` stay indexable)
3. GEO answer capsules on home + shop (`config/homepage.php` → `answer_capsules`)
4. Per-product answer capsules + Why/How/Where selection guidance on PDPs (`product_contents`; still no medical/dosing claims)
5. Lighthouse CI stubs: `.lighthouserc.cjs` + `.github/workflows/lighthouse.yml` (runs when `vars.LHCI_BASE_URL` is set)

## Phase 3+ (still deferred)

| Area | Why deferred |
|------|----------------|
| Keyword research APIs | Needs SEMrush/Ahrefs/DataForSEO credentials |
| Competitor traffic / DA reports | Would invent metrics without tooling |
| Full Inertia SSR Node sidecar | Heavier ops than Blade bootstrap |
| GA4 / GTM | Needs property IDs and consent policy |
| GBP / local citations | Offline / console work |
| 90-day content calendar + pillar farm | Editorial + compliance review |
| Link building / outreach | Ongoing ops, not code |
| hreflang multi-locale | Single `en-GB` market today |

## Compliance rules (always)

- Index only `Product::purchasable()` and published, non-placeholder content
- Never fabricate ratings or medical/dosing claims in meta, schema, or `llms.txt`
- Prefer first-party image URLs after object-storage migration (`docs/PRODUCT_IMAGE_OBJECT_STORAGE_MIGRATION.md`)
