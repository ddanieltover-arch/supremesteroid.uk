<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Account\WishlistService;
use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingEngine;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $cartService = app(CartService::class);
        $sessionId = $request->hasSession() ? $request->session()->getId() : null;

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role_label' => $request->user()->role?->label(),
                    'email_verified' => $request->user()->hasVerifiedEmail(),
                ] : null,
            ],
            'store' => [
                'name' => config('store.name', 'Supreme Steroids'),
                'legal_name' => config('store.legal_name', 'Supreme Steroids'),
                'support_email' => config('store.support_email', 'info@supremesteroid.uk'),
                'logo_url' => config('store.logo_url', '/assets/branding/supreme-steroids-logo.svg'),
                'domain' => config('store.domain'),
            ],
            'cart' => $cartService->summarize($cartService->findCart($request->user(), $sessionId)),
            'shippingOverview' => app(ShippingEngine::class)->publicOverview(),
            'wishlistProductIds' => $request->user()
                ? app(WishlistService::class)->productIds($request->user())
                : [],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
