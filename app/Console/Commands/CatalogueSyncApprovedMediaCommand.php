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

class CatalogueSyncApprovedMediaCommand extends Command
{
    protected $signature = 'catalogue:sync-approved-media
        {--execute : Perform copies (default dry-run outside testing)}
        {--limit=50 : Maximum records to process}
        {--actor= : Admin user email for audit}';

    protected $description = 'Copy APPROVED staged images that are not yet fulfilled into object storage.';

    public function handle(ApproveCatalogueImageAction $action): int
    {
        try {
            QualityAssuranceGuard::assertSafeEnvironment();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $execute = (bool) $this->option('execute') || app()->environment('testing');
        $limit = max(1, (int) $this->option('limit'));

        $images = CatalogueImportImage::query()
            ->with('importRecord.product')
            ->where('status', ImportImageStatus::APPROVED->value)
            ->whereNull('copied_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $this->info(($execute ? 'Syncing' : 'Dry-run').' '.$images->count().' APPROVED image(s).');

        if (! $execute) {
            foreach ($images as $image) {
                $this->line('- '.$image->id.' → '.(string) $image->source_url);
            }
            $this->warn('Pass --execute to copy into object storage.');

            return self::SUCCESS;
        }

        $actor = $this->resolveActor();
        $ok = 0;
        $failed = 0;

        foreach ($images as $image) {
            try {
                $action->fulfill($image, $actor);
                $ok++;
                $this->line('Copied '.$image->id);
            } catch (\Throwable $exception) {
                $failed++;
                $this->error('Failed '.$image->id.': '.$exception->getMessage());
            }
        }

        $this->info("Done. Copied {$ok}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
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
            throw new RuntimeException('No admin actor available for media sync audit.');
        }

        return $admin;
    }
}
