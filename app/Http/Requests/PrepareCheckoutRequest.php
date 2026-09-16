<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PrepareCheckoutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->request->remove('price');
        $this->request->remove('unit_price');
        $this->request->remove('shipping_amount');
        $this->request->remove('discount_amount');
        $this->request->remove('grand_total');
        $this->request->remove('total_amount');
        $this->request->remove('tax_amount');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'string', 'exists:products,id'],
            'items.*.variant_id' => ['nullable', 'string', 'exists:product_variants,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:99'],
            'shipping_destination' => ['required', 'array'],
            'shipping_destination.country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'shipping_destination.full_name' => ['required', 'string', 'max:150'],
            'shipping_destination.address_line_1' => ['required', 'string', 'max:150'],
            'shipping_destination.address_line_2' => ['nullable', 'string', 'max:150'],
            'shipping_destination.city' => ['required', 'string', 'max:100'],
            'shipping_destination.state_county' => ['nullable', 'string', 'max:100'],
            'shipping_destination.postal_code' => ['required', 'string', 'max:20'],
            'shipping_destination.phone' => ['nullable', 'string', 'max:30'],
            'shipping_method_code' => ['nullable', 'string', 'max:50'],
            'billing_destination' => ['nullable', 'array'],
            'billing_destination.country_code' => ['nullable', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'billing_destination.full_name' => ['nullable', 'string', 'max:150'],
            'billing_destination.address_line_1' => ['nullable', 'string', 'max:150'],
            'billing_destination.address_line_2' => ['nullable', 'string', 'max:150'],
            'billing_destination.city' => ['nullable', 'string', 'max:100'],
            'billing_destination.state_county' => ['nullable', 'string', 'max:100'],
            'billing_destination.postal_code' => ['nullable', 'string', 'max:20'],
            'billing_destination.phone' => ['nullable', 'string', 'max:30'],
            'customer_notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_destination.country_code.regex' => 'Country must be a two-letter ISO code such as GB.',
            'items.required' => 'Your cart is empty.',
        ];
    }
}
