<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Catalogue\ApproveCatalogueImageAction;
use App\Enums\ImportImageStatus;
use App\Models\CatalogueImportImage;
use App\Models\User;
use App\Support\QualityAssuranceGuard;
use Illuminate\Console\Command;
use RuntimeException;

class CatalogueApproveMediaCommand extends Command
{
    protected $signature = 'catalogue:approve-media
        {record : Catalogue import image UUID}
        {--execute : Persist object-storage copy (default is dry-run outside testing)}
        {--approve-only : Mark APPROVED without copying}
        {--actor= : Admin user email for audit}';

    protected $description = 'Approve and optionally copy a staged catalogue image into object storage.';

    public function handle(ApproveCatalogueImageAction $action): int
    {
        try {
            QualityAssuranceGuard::assertSafeEnvironment();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $image = CatalogueImportImage::query()->with('importRecord.product')->find($this->argument('record'));
        if (! $image) {
            $this->error('Import image record not found.');

            return self::FAILURE;
        }

        $actor = $this->resolveActor();
        $execute = (bool) $this->option('execute') || app()->environment('testing');

        if ($image->status !== ImportImageStatus::APPROVED) {
            if (! $execute && ! $this->option('approve-only')) {
                $this->warn('Dry-run: would mark image APPROVED and copy to object storage.');
                $this->line('Status: '.$image->status?->value);
                $this->line('Source: '.(string) $image->source_url);

                return self::SUCCESS;
            }

            $action->approve($image, $actor);
            $image->refresh();
            $this->info('Image marked APPROVED.');
        }

        if ($this->option('approve-only')) {
            return self::SUCCESS;
        }

        if (! $execute) {
            $this->warn('Dry-run: would copy APPROVED image to object storage. Pass --execute to run.');

            return self::SUCCESS;
        }

        try {
            $fulfilled = $action->fulfill($image->fresh(), $actor);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Image copied. Product image: '.(string) $fulfilled->product_image_id);

        return self::SUCCESS;
    }

    protected function resolveActor(): User
    {
        $email = $this->option('actor');
        if (is_string($email) && $email !== '') {
            $user = User::query()->where('email', $email)->first();
            if ($user) {
                return $user;
            }
        }

        $admin = User::query()->whereIn('role', ['SUPER_ADMIN', 'MANAGER'])->orderBy('created_at')->first();
        if (! $admin) {
            throw new RuntimeException('No admin actor available for media approval audit.');
        }

        return $admin;
    }
}
