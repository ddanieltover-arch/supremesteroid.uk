<?php

declare(strict_types=1);

namespace App\Actions\Catalogue;

use App\Enums\ImportImageStatus;
use App\Models\CatalogueImportImage;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class ApproveCatalogueImageAction
{
    public function __construct(
        protected ObjectStorageService $storage,
        protected AuditLogger $auditLogger,
    ) {}

    public function approve(CatalogueImportImage $image, User $actor, ?string $sourceName = null): CatalogueImportImage
    {
        if ($image->status === ImportImageStatus::REJECTED) {
            throw new InvalidArgumentException('Rejected images cannot be approved without a new review cycle.');
        }

        if (! filled($image->source_url) && $sourceName === null) {
            throw new InvalidArgumentException('An approved image requires a source reference.');
        }

        $image->status = ImportImageStatus::APPROVED;
        $image->approved_by = $actor->id;
        $image->approved_at = now();
        $image->rejection_reason = null;
        if ($sourceName !== null) {
            $image->source_name = $sourceName;
        } elseif (! filled($image->source_name)) {
            $image->source_name = $image->importRecord?->source_name ?? 'import';
        }
        $image->save();

        $this->auditLogger->log(
            eventType: 'catalogue_import.media_approved',
            auditable: $image,
            newValues: [
                'status' => ImportImageStatus::APPROVED->value,
                'source_url' => $image->source_url,
                'source_name' => $image->source_name,
            ],
            actor: $actor,
            reason: 'Admin approved staged catalogue image for persistent copy.'
        );

        return $image->fresh();
    }

    public function reject(CatalogueImportImage $image, User $actor, string $reason): CatalogueImportImage
    {
        $image->status = ImportImageStatus::REJECTED;
        $image->rejection_reason = $reason;
        $image->approved_by = $actor->id;
        $image->approved_at = now();
        $image->save();

        $this->auditLogger->log(
            eventType: 'catalogue_import.media_rejected',
            auditable: $image,
            newValues: ['status' => ImportImageStatus::REJECTED->value, 'reason' => $reason],
            actor: $actor,
            reason: $reason
        );

        return $image->fresh();
    }

    /**
     * Copy an APPROVED staged image into object storage and attach ProductImage.
     * Idempotent: a second call returns the existing ProductImage.
     */
    public function fulfill(
        CatalogueImportImage $image,
        User $actor,
        ?UploadedFile $localOverride = null
    ): CatalogueImportImage {
        if ($image->status !== ImportImageStatus::APPROVED) {
            throw new InvalidArgumentException('Only APPROVED staged images may be copied to object storage.');
        }

        if ($image->isCopied()) {
            $existing = ProductImage::query()->find($image->product_image_id);
            if ($existing) {
                return $image->fresh(['productImage']);
            }
        }

        $product = $image->importRecord?->product;
        if (! $product instanceof Product) {
            throw new InvalidArgumentException('Staged image has no linked product draft.');
        }

        try {
            [$contents, $filename, $mime, $extension] = $this->resolveBinary($image, $localOverride);
            $this->assertSafeImage($contents, $mime);

            return DB::transaction(function () use ($image, $actor, $product, $contents, $filename, $mime, $extension) {
                $variantId = $this->resolveAndValidateVariantId($image, $product);

                $stored = $this->storage->storeValidatedBinary(
                    contents: $contents,
                    originalFilename: $filename,
                    mimeType: $mime,
                    sizeBytes: strlen($contents),
                    extension: $extension,
                    collection: (string) config('filesystems.uploads.product_images_collection', 'product_images'),
                    uploader: $actor,
                    fileable: $product,
                );

                if ($image->is_primary) {
                    $primaryQuery = ProductImage::query()
                        ->where('product_id', $product->id)
                        ->where('is_primary', true);
                    if ($variantId !== null) {
                        $primaryQuery->where('product_variant_id', $variantId);
                    } else {
                        $primaryQuery->whereNull('product_variant_id');
                    }
                    $primaryQuery->update(['is_primary' => false]);
                }

                $productImage = ProductImage::query()->create([
                    'product_id' => $product->id,
                    'product_variant_id' => $variantId,
                    'url' => $stored->path,
                    'alt_text' => $image->alt_text ?: $product->name,
                    'display_order' => $image->sort_order,
                    'is_primary' => $image->is_primary,
                    'caption' => null,
                ]);

                if ($image->product_variant_id !== $variantId) {
                    $image->product_variant_id = $variantId;
                }

                $image->product_image_id = $productImage->id;
                $image->copied_at = now();
                $image->transfer_failure_reason = null;
                $image->transfer_attempts = (int) $image->transfer_attempts + 1;
                $image->save();

                $this->auditLogger->log(
                    eventType: 'catalogue_import.media_copied',
                    auditable: $image,
                    newValues: [
                        'product_image_id' => $productImage->id,
                        'product_variant_id' => $variantId,
                        'storage_path' => $stored->path,
                        'disk' => $stored->disk,
                    ],
                    actor: $actor,
                    reason: 'Approved catalogue image copied to persistent object storage.'
                );

                return $image->fresh(['productImage']);
            });
        } catch (\Throwable $exception) {
            $image->transfer_attempts = (int) $image->transfer_attempts + 1;
            $image->transfer_failure_reason = Str::limit($exception->getMessage(), 500);
            $image->copied_at = null;
            $image->save();

            $this->auditLogger->log(
                eventType: 'catalogue_import.media_transfer_failed',
                auditable: $image,
                newValues: ['reason' => $image->transfer_failure_reason],
                actor: $actor,
                reason: 'Catalogue image transfer failed.'
            );

            throw new RuntimeException('Image transfer failed: '.$exception->getMessage(), 0, $exception);
        }
    }

    protected function resolveAndValidateVariantId(CatalogueImportImage $image, Product $product): ?string
    {
        $variantId = $image->product_variant_id;
        if ($variantId === null && filled($image->source_variant_sku)) {
            $variantId = $product->variants()
                ->where('sku', strtoupper((string) $image->source_variant_sku))
                ->value('id');
        }

        if ($variantId === null) {
            return null;
        }

        $belongs = $product->variants()->whereKey($variantId)->exists();
        if (! $belongs) {
            throw new InvalidArgumentException('Cannot bind image to a variant that does not belong to this product.');
        }

        return (string) $variantId;
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function resolveBinary(CatalogueImportImage $image, ?UploadedFile $localOverride): array
    {
        if ($localOverride !== null) {
            $path = $localOverride->getRealPath();
            if ($path === false) {
                throw new InvalidArgumentException('Uploaded override file could not be read.');
            }

            return [
                (string) file_get_contents($path),
                $localOverride->getClientOriginalName(),
                (string) ($localOverride->getMimeType() ?: 'application/octet-stream'),
                strtolower((string) ($localOverride->guessExtension() ?: 'bin')),
            ];
        }

        $source = (string) $image->source_url;
        if ($source === '') {
            throw new InvalidArgumentException('Approved image is missing a source reference.');
        }

        if (is_file($source)) {
            $mime = (string) (mime_content_type($source) ?: 'application/octet-stream');
            $extension = strtolower((string) pathinfo($source, PATHINFO_EXTENSION));

            return [(string) file_get_contents($source), basename($source), $mime, $extension];
        }

        if (str_starts_with($source, '/') && is_file(public_path(ltrim($source, '/')))) {
            $absolute = public_path(ltrim($source, '/'));
            $mime = (string) (mime_content_type($absolute) ?: 'application/octet-stream');
            $extension = strtolower((string) pathinfo($absolute, PATHINFO_EXTENSION));

            return [(string) file_get_contents($absolute), basename($absolute), $mime, $extension];
        }

        if (preg_match('#^https?://#i', $source) === 1) {
            if (! filter_var(config('catalogue.import.allow_remote_image_copy', false), FILTER_VALIDATE_BOOL)
                && ! app()->environment(['local', 'testing', 'qa'])) {
                throw new InvalidArgumentException('Remote image copy is disabled. Provide a local approved fixture or enable catalogue.import.allow_remote_image_copy.');
            }

            $safeSource = str_replace(' ', '%20', $source);

            $context = stream_context_create([
                'http' => ['timeout' => 15, 'follow_location' => 1, 'max_redirects' => 3],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $contents = @file_get_contents($safeSource, false, $context);
            if ($contents === false || $contents === '') {
                throw new RuntimeException('Unable to download approved source image.');
            }

            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $mime = (string) ($finfo->buffer($contents) ?: 'application/octet-stream');
            $extension = match ($mime) {
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                default => strtolower((string) pathinfo(parse_url($source, PHP_URL_PATH) ?: 'image.bin', PATHINFO_EXTENSION)) ?: 'bin',
            };

            $rawBaseName = basename((string) (parse_url($source, PHP_URL_PATH) ?: 'image.'.$extension));
            $filename = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $rawBaseName) ?: 'image.'.$extension;

            return [$contents, $filename, $mime, $extension];
        }

        throw new InvalidArgumentException('Unsupported source image reference. Use a local fixture path or an approved remote URL.');
    }

    protected function assertSafeImage(string $contents, string $mime): void
    {
        if (! str_starts_with(strtolower($mime), 'image/')) {
            throw new InvalidArgumentException('Only image MIME types may be copied into product media.');
        }

        if (@getimagesizefromstring($contents) === false) {
            throw new InvalidArgumentException('The approved file is not a valid image.');
        }
    }
}
