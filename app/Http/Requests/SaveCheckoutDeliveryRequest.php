<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SaveCheckoutDeliveryRequest extends FormRequest
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
            'shipping_method_code' => ['required', 'string', 'max:50'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'shipping_method_code.required' => 'Please choose a delivery method.',
        ];
    }
}
