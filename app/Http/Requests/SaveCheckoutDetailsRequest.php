<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCheckoutDetailsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
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
            'email' => [$this->user() ? 'nullable' : 'required', 'email', 'max:255'],
            'address_id' => ['nullable', 'uuid'],
            'save_address' => ['sometimes', 'boolean'],
            'shipping_destination' => ['required_without:address_id', 'array'],
            'shipping_destination.country_code' => ['required_without:address_id', 'string', 'size:2', 'regex:/^[A-Za-z]{2}$/'],
            'shipping_destination.full_name' => ['required_without:address_id', 'string', 'max:150'],
            'shipping_destination.address_line_1' => ['required_without:address_id', 'string', 'max:150'],
            'shipping_destination.address_line_2' => ['nullable', 'string', 'max:150'],
            'shipping_destination.city' => ['required_without:address_id', 'string', 'max:100'],
            'shipping_destination.state_county' => ['nullable', 'string', 'max:100'],
            'shipping_destination.postal_code' => ['required_without:address_id', 'string', 'max:20'],
            'shipping_destination.phone' => ['required_without:address_id', 'string', 'max:30'],
            'shipping_method_code' => ['nullable', 'string', 'max:50'],
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
            'shipping_destination.full_name.required_without' => 'Please enter the delivery name.',
            'shipping_destination.address_line_1.required_without' => 'Please enter the first line of the delivery address.',
            'shipping_destination.city.required_without' => 'Please enter the delivery city.',
            'shipping_destination.postal_code.required_without' => 'Please enter the delivery postcode.',
            'shipping_destination.phone.required_without' => 'Please enter a phone number for delivery.',
        ];
    }
}
