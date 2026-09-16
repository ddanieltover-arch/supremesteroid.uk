<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitCryptoPaymentProofRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'crypto_network' => ['required', 'string', 'max:20'],
            'crypto_transaction_hash' => ['required', 'string', 'max:120'],
            'crypto_recipient_wallet' => ['required', 'string', 'max:120'],
            'crypto_submitted_amount' => ['required', 'numeric', 'min:0.00000001'],
            'submitted_fiat_amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
