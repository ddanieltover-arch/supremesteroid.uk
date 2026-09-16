<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use App\Services\Cart\CartService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->request->remove('unit_price');
        $this->request->remove('shipping_amount');
        $this->request->remove('discount_amount');
        $this->request->remove('grand_total');
        $this->request->remove('total_amount');
        $this->request->remove('tax_amount');

        if (! $this->filled('items') && ! $this->filled('checkout_token') && $this->user()) {
            $cartService = app(CartService::class);
            $cart = $cartService->getOrCreateCart($this->user(), $this->session()->getId());
            $calculated = $cartService->calculateCart($cart);

            $this->merge([
                'items' => array_map(static fn (array $item) => [
                    'product_id' => $item['product_id'],
                    'variant_id' => $item['variant_id'],
                    'quantity' => $item['quantity'],
                ], $calculated['items']),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'checkout_token' => ['nullable', 'string', 'max:64', 'exists:checkout_sessions,token'],
            'items' => ['required_without:checkout_token', 'array', 'min:1'],
            'items.*.product_id' => ['required_with:items', 'string', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'string', 'exists:product_variants,id'],
            'items.*.quantity' => ['required_with:items', 'integer', 'min:1', 'max:99'],
            'shipping_destination' => ['required_without:checkout_token', 'array'],
            'shipping_destination.country_code' => ['required_without:checkout_token', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'shipping_destination.full_name' => ['required_without:checkout_token', 'string', 'max:150'],
            'shipping_destination.address_line_1' => ['required_without:checkout_token', 'string', 'max:150'],
            'shipping_destination.address_line_2' => ['nullable', 'string', 'max:150'],
            'shipping_destination.city' => ['required_without:checkout_token', 'string', 'max:100'],
            'shipping_destination.state_county' => ['nullable', 'string', 'max:100'],
            'shipping_destination.postal_code' => ['required_without:checkout_token', 'string', 'max:20'],
            'shipping_destination.phone' => ['nullable', 'string', 'max:30'],
            'shipping_method_code' => ['nullable', 'string', 'max:50'],
            'payment_method' => ['required', 'string', Rule::in([
                PaymentMethodType::BANK_TRANSFER->value,
                PaymentMethodType::CRYPTOCURRENCY->value,
            ])],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
