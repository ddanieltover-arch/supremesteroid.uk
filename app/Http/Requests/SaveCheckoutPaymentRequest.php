<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PaymentMethodType;
use App\Models\CheckoutSession;
use App\Services\Checkout\CheckoutFlowService;
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
        return [
            'payment_method' => ['required', 'string', Rule::in($this->allowedPaymentMethods())],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedPaymentMethods(): array
    {
        $flow = app(CheckoutFlowService::class);
        $token = $this->session()->get('checkout_token');
        $snapshot = null;

        if (is_string($token) && $token !== '') {
            $session = CheckoutSession::query()->where('token', $token)->first();
            $snapshot = is_array($session?->snapshot) ? $session->snapshot : null;
        }

        $allowed = $flow->allowedPaymentMethodCodes($flow->resolveCheckoutGrandTotal($snapshot));

        if ($allowed === []) {
            return [
                PaymentMethodType::CRYPTOCURRENCY->value,
            ];
        }

        return $allowed;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.in' => 'That payment method is not available for this order total.',
        ];
    }
}
