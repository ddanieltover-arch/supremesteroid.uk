<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class NewsletterSubscriber extends Model
{
    use HasUuids;

    protected $fillable = [
        'email',
        'consented_at',
        'consent_ip',
        'consent_user_agent',
        'source',
        'provider_status',
    ];

    protected function casts(): array
    {
        return [
            'consented_at' => 'datetime',
        ];
    }
}
