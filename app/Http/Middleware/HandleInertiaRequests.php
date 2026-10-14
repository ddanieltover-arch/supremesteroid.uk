<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Account\WishlistService;
use App\Services\Cart\CartService;
use App\Services\Catalogue\CatalogueDiscoveryService;
use App\Services\Storefront\HomepageService;
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
        $wishlistIds = $request->user()
            ? app(WishlistService::class)->productIds($request->user())
            : [];
        $chrome = app(HomepageService::class)->chrome();
        $announcement = $chrome['announcement'] ?? null;
        if (is_array($announcement) && $request->hasSession()) {
            $dismissed = $request->session()->get('announcement_dismissed');
            if (is_string($dismissed) && ($announcement['signature'] ?? null) === $dismissed) {
                $chrome['announcement'] = null;
            }
        }

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'email_verified' => $request->user()->hasVerifiedEmail(),
                ] : null,
            ],
            'store' => [
                'name' => config('store.name', 'Supreme Steroids'),
                'legal_name' => config('store.legal_name', 'Supreme Steroids'),
                'support_email' => config('store.support_email', 'info@supremesteroid.uk'),
                'logo_url' => config('store.logo_url', '/assets/branding/supreme-steroids-logo.png'),
                'domain' => config('store.domain'),
                'address' => config('store.business_address'),
                'support_phone' => config('store.support_phone'),
            ],
            'chrome' => $chrome,
            'cart' => $cartService->summarize(
                $request->hasSession()
                    ? $cartService->resolveRequestCart($request)
                    : $cartService->findCart($request->user(), $sessionId)
            ),
            'wishlistProductIds' => $wishlistIds,
            'wishlistCount' => count($wishlistIds),
            'catalogueNav' => app(CatalogueDiscoveryService::class)->navigation(),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
