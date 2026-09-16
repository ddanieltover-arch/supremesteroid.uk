<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Services\Cart\CartService;
use App\Services\Shipping\ShippingEngine;
use App\Support\IsoCountry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use InvalidArgumentException;

class ShippingQuoteController extends Controller
{
    public function __construct(
        protected ShippingEngine $shippingEngine,
        protected CartService $cartService
    ) {}

    public function quote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'country_code' => ['required', 'string', 'size:2'],
            'subtotal' => ['prohibited'],
            'shipping_amount' => ['prohibited'],
        ]);

        try {
            $countryCode = IsoCountry::normalize($validated['country_code']);
        } catch (InvalidArgumentException) {
            return response()->json([
                'message' => 'Please provide a valid two-letter country code.',
                'code' => 'INVALID_COUNTRY',
            ], 422);
        }

        $cart = $this->cartService->findCart(
            $request->user(),
            $request->hasSession() ? $request->session()->getId() : null
        );
        $cartData = $this->cartService->summarize($cart);
        $subtotal = $cartData['totals']['subtotal_amount'] ?? '0.00';
        $rates = $this->shippingEngine->calculateRates($subtotal, $countryCode);

        return response()->json([
            'country_code' => $countryCode,
            'subtotal' => $subtotal,
            'rates' => $rates,
            'shipping_eligibility' => $cartData['shipping_eligibility'],
        ]);
    }
}
