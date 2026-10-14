<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Support\Facades\Log;

class LogNewsletterProvider implements NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): void
    {
        Log::info('newsletter.subscribed', [
            'id' => $subscriber->id,
            'source' => $subscriber->source,
        ]);
    }
}
