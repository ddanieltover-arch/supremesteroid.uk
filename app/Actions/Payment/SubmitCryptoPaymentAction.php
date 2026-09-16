<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;

class SubmitCryptoPaymentAction
{
    public function __construct(
        protected PaymentStateMachine $paymentStateMachine
    ) {}

    /**
     * @param array{
     *     crypto_network: string,
     *     crypto_transaction_hash: string,
     *     crypto_recipient_wallet: string,
     *     crypto_submitted_amount: float,
     *     submitted_fiat_amount: float
     * } $data
     */
    public function execute(Payment $payment, User $customer, array $data): PaymentSubmission
    {
        return DB::transaction(function () use ($payment, $customer, $data) {
            $submission = PaymentSubmission::create([
                'payment_id' => $payment->id,
                'submission_type' => 'CRYPTOCURRENCY',
                'submitted_amount' => $data['submitted_fiat_amount'],
                'currency' => $payment->currency ?? 'GBP',
                'crypto_network' => $data['crypto_network'],
                'crypto_transaction_hash' => $data['crypto_transaction_hash'],
                'crypto_recipient_wallet' => $data['crypto_recipient_wallet'],
                'crypto_submitted_amount' => $data['crypto_submitted_amount'],
                'review_status' => PaymentStatus::PAYMENT_SUBMITTED,
            ]);

            $this->paymentStateMachine->transition(
                $payment,
                PaymentStatus::PAYMENT_SUBMITTED,
                $customer,
                "Customer submitted cryptocurrency payment on {$data['crypto_network']}: {$data['crypto_transaction_hash']}"
            );

            DB::afterCommit(function () use ($payment) {
                app(\App\Services\Mail\CustomerMailer::class)->paymentProofReceived($payment);
            });

            return $submission;
        });
    }
}
