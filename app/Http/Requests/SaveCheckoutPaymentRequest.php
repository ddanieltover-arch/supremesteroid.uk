<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCheckoutPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $allowed = array_values(array_filter(array_map(
            static fn (array $method) => $method['code'] ?? null,
            config('payments.methods', [])
        )));

        if ($allowed === []) {
            $allowed = [
                PaymentMethodType::BANK_TRANSFER->value,
                PaymentMethodType::CRYPTOCURRENCY->value,
            ];
        }

        return [
            'payment_method' => ['required', 'string', Rule::in($allowed)],
        ];
    }
}
