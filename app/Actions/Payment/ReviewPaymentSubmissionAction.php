<?php

declare(strict_types=1);

namespace App\Actions\Payment;

use App\Models\PaymentSubmission;
use App\Models\User;
use App\Services\Payment\PaymentStateMachine;

class ReviewPaymentSubmissionAction
{
    public function __construct(
        protected PaymentStateMachine $paymentStateMachine
    ) {}

    public function execute(
        PaymentSubmission $submission,
        bool $approved,
        User $reviewer,
        string $adminNotes
    ): PaymentSubmission {
        return $this->paymentStateMachine->reviewSubmission(
            $submission,
            $approved,
            $reviewer,
            $adminNotes
        );
    }
}
