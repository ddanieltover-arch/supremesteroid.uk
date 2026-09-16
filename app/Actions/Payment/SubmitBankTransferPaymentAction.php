<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Payment\PaymentStateMachine;
use Illuminate\Support\Facades\DB;

class SubmitBankTransferPaymentAction
{
    public function __construct(
        protected PaymentStateMachine $paymentStateMachine
    ) {}

    /**
     * @param array{
     *     bank_payment_reference: string,
     *     sender_account_name: string,
     *     submitted_amount: float,
     *     transfer_date: string,
     *     proof_document_url?: ?string
     * } $data
     */
    public function execute(Payment $payment, User $customer, array $data): PaymentSubmission
    {
        return DB::transaction(function () use ($payment, $customer, $data) {
            $submission = PaymentSubmission::create([
                'payment_id' => $payment->id,
                'submission_type' => 'BANK_TRANSFER',
                'submitted_amount' => $data['submitted_amount'],
                'currency' => $payment->currency ?? 'GBP',
                'bank_payment_reference' => $data['bank_payment_reference'],
                'sender_account_name' => $data['sender_account_name'],
                'transfer_date' => $data['transfer_date'],
                'proof_document_url' => $data['proof_document_url'] ?? null,
                'proof_file_id' => $data['proof_file_id'] ?? null,
                'review_status' => PaymentStatus::PAYMENT_SUBMITTED,
            ]);

            $this->paymentStateMachine->transition(
                $payment,
                PaymentStatus::PAYMENT_SUBMITTED,
                $customer,
                'Customer submitted bank transfer details for reference ' . $data['bank_payment_reference']
            );

            DB::afterCommit(function () use ($payment) {
                app(\App\Services\Mail\CustomerMailer::class)->paymentProofReceived($payment);
            });

            return $submission;
        });
    }
}
