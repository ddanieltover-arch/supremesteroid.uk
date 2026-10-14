<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Services\Marketing\NewsletterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class NewsletterController extends Controller
{
    public function __invoke(Request $request, NewsletterService $newsletter): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'consent' => ['accepted'],
        ]);

        $newsletter->subscribe($validated['email'], $request);

        return back()->with('success', 'Thank you. You are subscribed with recorded consent.');
    }
}
