<?php

declare(strict_types=1);

namespace App\Services\Mail;

use App\Mail\CustomerMessage;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CustomerMailer
{
    public function orderPlaced(Order $order): void
    {
        $this->send(
            $order->customer_email_snapshot ?: $order->user?->email,
            'Order '.$order->order_number.' received',
            'We received your order '.$order->order_number.'. Payment is pending review. This message does not mean the order is paid.'
        );
    }

    public function paymentProofReceived(Payment $payment): void
    {
        $order = $payment->order;
        if (! $order) {
            return;
        }

        $this->send(
            $order->customer_email_snapshot ?: $order->user?->email,
            'Payment submission received for '.$order->order_number,
            'We received your payment submission for '.$order->order_number.'. Staff must review it before the order can be marked paid.'
        );
    }

    public function paymentReviewed(Payment $payment, bool $approved): void
    {
        $order = $payment->order;
        if (! $order) {
            return;
        }

        $this->send(
            $order->customer_email_snapshot ?: $order->user?->email,
            ($approved ? 'Payment approved' : 'Payment not approved').' for '.$order->order_number,
            $approved
                ? 'Payment for '.$order->order_number.' was approved by staff.'
                : 'Payment for '.$order->order_number.' was not approved. Please review the instructions on your order page.'
        );
    }

    public function orderShipped(Order $order): void
    {
        $this->send(
            $order->customer_email_snapshot ?: $order->user?->email,
            'Order '.$order->order_number.' shipped',
            'Order '.$order->order_number.' has been marked shipped.'
        );
    }

    protected function send(?string $email, string $subject, string $body): void
    {
        if (! is_string($email) || $email === '' || ! config('mail.default')) {
            return;
        }

        try {
            Mail::to($email)->send(new CustomerMessage($subject, $body));
        } catch (\Throwable $exception) {
            Log::warning('Customer email could not be sent', [
                'mailable' => CustomerMessage::class,
                'exception' => $exception::class,
            ]);
        }
    }
}
