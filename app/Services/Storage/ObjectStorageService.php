<?php

declare(strict_types=1);

namespace App\Services\Storage;

use App\Models\StoredFile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ObjectStorageService
{
    public function diskName(): string
    {
        $disk = (string) config('filesystems.cloud', config('filesystems.default', 'local'));

        return $disk !== '' ? $disk : 'local';
    }

    public function storeUploadedFile(
        UploadedFile $file,
        string $collection,
        ?User $uploader = null,
        ?object $fileable = null
    ): StoredFile {
        $this->assertAllowedUpload($file);

        $collection = trim($collection, '/');
        $this->assertCollection($collection);

        $safeName = $this->safeFilename($file->getClientOriginalName());
        $extension = strtolower((string) ($file->guessExtension() ?: pathinfo($file->getClientOriginalName(), PATHINFO_EXTENSION)));
        $extension = preg_replace('/[^a-z0-9]/', '', $extension) ?: 'bin';
        $key = $collection.'/'.now()->format('Y/m/d').'/'.Str::uuid().'-'.$safeName.'.'.$extension;
        $key = str_replace('\\', '/', $key);

        if (str_contains($key, '..') || str_starts_with($key, '/')) {
            throw new InvalidArgumentException('The storage key is not allowed.');
        }

        $disk = $this->diskName();
        $this->assertProductionDisk($disk);

        Storage::disk($disk)->putFileAs(
            dirname($key),
            $file,
            basename($key),
            ['visibility' => $this->visibilityFor($collection)]
        );

        return StoredFile::create([
            'disk' => $disk,
            'path' => $key,
            'original_filename' => Str::limit($file->getClientOriginalName(), 255, ''),
            'mime_type' => (string) ($file->getMimeType() ?: $file->getClientMimeType()),
            'size_bytes' => (int) $file->getSize(),
            'collection' => $collection,
            'fileable_type' => $fileable ? $fileable::class : null,
            'fileable_id' => $fileable->id ?? null,
            'uploaded_by' => $uploader?->id,
            'uploaded_at' => now(),
        ]);
    }

    public function temporaryUrl(StoredFile $file, ?int $minutes = null): string
    {
        $minutes ??= (int) config('filesystems.uploads.signed_url_minutes', 30);
        $disk = Storage::disk($file->disk);

        if (method_exists($disk, 'temporaryUrl')) {
            try {
                return $disk->temporaryUrl($file->path, now()->addMinutes($minutes));
            } catch (\Throwable) {
                // Local disks do not support temporary URLs.
            }
        }

        if ($this->isPrivateCollection($file->collection)) {
            throw new InvalidArgumentException('A signed URL could not be created for this private file.');
        }

        return $disk->url($file->path);
    }

    public function download(StoredFile $file): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        return Storage::disk($file->disk)->response(
            $file->path,
            'payment-proof',
            [
                'Content-Type' => $file->mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    public function delete(StoredFile $file): void
    {
        Storage::disk($file->disk)->delete($file->path);
        $file->delete();
    }

    protected function assertAllowedUpload(UploadedFile $file): void
    {
        $maxKilobytes = (int) config('filesystems.uploads.max_kilobytes', 10240);
        $allowed = config('filesystems.uploads.allowed_mimes', ['jpg', 'jpeg', 'png', 'webp', 'pdf']);

        if ($file->getSize() > $maxKilobytes * 1024) {
            throw new InvalidArgumentException('The uploaded file exceeds the maximum allowed size.');
        }

        $extension = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension()));
        if (! in_array($extension, $allowed, true)) {
            throw new InvalidArgumentException('That file type is not allowed.');
        }
    }

    protected function safeFilename(string $original): string
    {
        $basename = pathinfo($original, PATHINFO_FILENAME);
        $slug = Str::slug($basename);

        return $slug !== '' ? Str::limit($slug, 80, '') : 'upload';
    }

    protected function visibilityFor(string $collection): string
    {
        return $this->isPrivateCollection($collection) ? 'private' : 'public';
    }

    protected function isPrivateCollection(string $collection): bool
    {
        return in_array($collection, ['payment_proofs'], true);
    }

    protected function assertCollection(string $collection): void
    {
        $allowed = [
            (string) config('filesystems.uploads.payment_proofs_collection', 'payment_proofs'),
            (string) config('filesystems.uploads.product_images_collection', 'product_images'),
            (string) config('filesystems.uploads.cms_collection', 'cms'),
        ];

        if (! in_array($collection, $allowed, true) || str_contains($collection, '..') || str_contains($collection, '/')) {
            throw new InvalidArgumentException('That upload collection is not allowed.');
        }
    }

    protected function assertProductionDisk(string $disk): void
    {
        if (app()->environment('production') && in_array($disk, ['local', 'public'], true)) {
            throw new InvalidArgumentException('Production uploads must use persistent object storage.');
        }
    }
}
