<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class HomepageContent extends Model
{
    use HasUuids;

    protected $fillable = [
        'announcement_enabled',
        'announcement_message',
        'announcement_use_shipping_threshold',
        'hero_eyebrow',
        'hero_headline',
        'hero_description',
        'hero_primary_label',
        'hero_primary_url',
        'hero_secondary_label',
        'hero_secondary_url',
        'editorial_enabled',
        'editorial_label',
        'editorial_heading',
        'editorial_body',
        'editorial_cta_label',
        'editorial_cta_url',
        'editorial_image_url',
        'newsletter_enabled',
        'newsletter_heading',
        'newsletter_body',
        'seo_title',
        'seo_description',
        'section_order',
        'social_links',
    ];

    protected function casts(): array
    {
        return [
            'announcement_enabled' => 'boolean',
            'announcement_use_shipping_threshold' => 'boolean',
            'editorial_enabled' => 'boolean',
            'newsletter_enabled' => 'boolean',
            'section_order' => 'array',
            'social_links' => 'array',
        ];
    }
}
