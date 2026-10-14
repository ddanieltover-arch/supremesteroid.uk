<?php

declare(strict_types=1);

namespace App\Services\Marketing;

use App\Contracts\NewsletterProvider;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\Request;

class NewsletterService
{
    public function __construct(
        protected NewsletterProvider $provider
    ) {}

    public function subscribe(string $email, Request $request, string $source = 'homepage'): NewsletterSubscriber
    {
        $subscriber = NewsletterSubscriber::query()->firstOrNew(['email' => $email]);
        $subscriber->fill([
            'consented_at' => now(),
            'consent_ip' => $request->ip(),
            'consent_user_agent' => substr((string) $request->userAgent(), 0, 500),
            'source' => $source,
            'provider_status' => 'pending',
        ]);
        $subscriber->save();

        $this->provider->subscribe($subscriber);

        $subscriber->provider_status = 'accepted';
        $subscriber->save();

        return $subscriber;
    }
}
