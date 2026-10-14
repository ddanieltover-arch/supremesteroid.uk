<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class AnnouncementController extends Controller
{
    public function dismiss(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'signature' => ['required', 'string', 'size:64'],
        ]);

        $request->session()->put('announcement_dismissed', $validated['signature']);

        return back();
    }
}
