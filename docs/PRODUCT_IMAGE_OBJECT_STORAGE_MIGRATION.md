# Product image object-storage migration (SEO)

Catalogue product images must be served from first-party object storage (S3/R2 + public CDN URL such as `https://assets.supremesteroid.uk`), not BunnyCDN hotlinks. The database stores object **keys** in `product_images.url`; bytes live in the bucket.

## Preconditions

Confirm production (or the target environment) has:

| Variable | Expected |
|----------|----------|
| `OBJECT_STORAGE_DISK` | `s3` or `r2` |
| Bucket credentials | `AWS_*` or `R2_*` |
| Public base URL | `AWS_URL` or `R2_URL` (e.g. `https://assets.supremesteroid.uk`) |

Do **not** run `--execute` in production while `OBJECT_STORAGE_DISK` is `local` / `public`. The Artisan command refuses that.

## Commands

Dry-run (count + sample; writes audit, no DB/blob changes):

```bash
php artisan catalogue:migrate-cdn-images-to-object-storage
php artisan catalogue:migrate-cdn-images-to-object-storage --limit=25
```

Execute in batches:

```bash
php artisan catalogue:migrate-cdn-images-to-object-storage --execute --limit=50 --delay-ms=400
```

SEO-only rewrite (BunnyCDN `seo_metadata.og_image_url` → primary image `publicUrl()`, or clear):

```bash
php artisan catalogue:migrate-cdn-images-to-object-storage --execute --seo-only
```

Audit path: `storage/app/catalogue-import/audits/cdn-object-storage-migrate.json`.

## What it migrates

- `product_images.url` pointing at `supremestd.b-cdn.net` / `supremesteroids.co.uk`
- `/storage/product_images/...` rows whose original remote is in the CDN fallback map

Skips rows that already look like object keys (`product_images/Y/m/d/{uuid}.ext`) or first-party absolute URLs.

On `--execute` it also:

1. Creates `stored_files` rows via `ObjectStorageService`
2. Rewrites BunnyCDN `seo_metadata.og_image_url` values
3. Busts product detail / nav / homepage caches that embed image URLs

## Ops sequence

1. Dry-run; confirm candidate count and sample remotes
2. Execute with `--limit` until candidates reach 0
3. Inspect `failed` in the audit; retry after fixing hosts/404s
4. Smoke SEO:
   - Shop card image loads from first-party host
   - PDP gallery loads
   - View source: `og:image` is not `b-cdn.net`
   - Product JSON-LD `image` uses first-party URLs
5. Leave `resources/catalogue/cdn-stored-to-remote.json` until a clean full pass; shrink/remove in a follow-up

## Related

- Admin fulfill path already uses object storage: `ApproveCatalogueImageAction::fulfill`
- Legacy local-disk localize (not for Vercel): `catalogue:localize-cdn-images`
