<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\NewsletterSubscriber;

interface NewsletterProvider
{
    public function subscribe(NewsletterSubscriber $subscriber): void;
}
