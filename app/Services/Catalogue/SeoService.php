<?php

declare(strict_types=1);

namespace App\Services\Catalogue;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SeoService
{
    /**
     * @param  array<string, mixed>  $overrides
     * @return array{title: string, description: string, canonical: string, robots: string, og_title: string, og_description: string, og_image: ?string}
     */
    public function forPage(
        Request $request,
        string $title,
        string $description,
        array $overrides = [],
        ?Model $model = null
    ): array {
        $record = $model?->seoMetadata;
        $canonical = $overrides['canonical'] ?? $request->url();

        $resolvedTitle = $record->meta_title ?? $title;
        $resolvedDescription = $record->meta_description ?? $description;

        return [
            'title' => $resolvedTitle,
            'description' => Str::limit(trim($resolvedDescription), 160),
            'canonical' => $record->canonical_url ?? $canonical,
            'robots' => $record->robots_directive ?? ($overrides['robots'] ?? 'index,follow'),
            'og_title' => $record->og_title ?? $resolvedTitle,
            'og_description' => Str::limit(trim($resolvedDescription), 200),
            'og_image' => $record->og_image_url ?? ($overrides['og_image'] ?? null),
        ];
    }
}
