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
            email: $order->customer_email_snapshot ?: $order->user?->email,
            subject: 'Order '.$order->order_number.' received',
            headline: 'We received your order',
            bodyLines: [
                'Thanks — order '.$order->order_number.' is confirmed on our system.',
                'Payment is pending review. This message does not mean the order is paid.',
            ],
            order: $order,
        );
    }

    public function paymentProofReceived(Payment $payment): void
    {
        $order = $payment->order;
        if (! $order) {
            return;
        }

        $this->send(
            email: $order->customer_email_snapshot ?: $order->user?->email,
            subject: 'Payment submission received for '.$order->order_number,
            headline: 'Payment submission received',
            bodyLines: [
                'We received your payment submission for order '.$order->order_number.'.',
                'Staff must review it before the order can be marked paid.',
            ],
            order: $order,
        );
    }

    public function paymentReviewed(Payment $payment, bool $approved): void
    {
        $order = $payment->order;
        if (! $order) {
            return;
        }

        $this->send(
            email: $order->customer_email_snapshot ?: $order->user?->email,
            subject: ($approved ? 'Payment approved' : 'Payment not approved').' for '.$order->order_number,
            headline: $approved ? 'Payment approved' : 'Payment not approved',
            bodyLines: $approved
                ? [
                    'Payment for order '.$order->order_number.' was approved by staff.',
                    'We will prepare your order for dispatch.',
                ]
                : [
                    'Payment for order '.$order->order_number.' was not approved.',
                    'Please review the instructions on your order page and submit again if needed.',
                ],
            order: $order,
        );
    }

    public function orderShipped(Order $order): void
    {
        $this->send(
            email: $order->customer_email_snapshot ?: $order->user?->email,
            subject: 'Order '.$order->order_number.' shipped',
            headline: 'Your order has shipped',
            bodyLines: [
                'Order '.$order->order_number.' has been marked shipped.',
                'You can view shipping updates on your order page.',
            ],
            order: $order,
        );
    }

    /**
     * @param  list<string>  $bodyLines
     */
    protected function send(
        ?string $email,
        string $subject,
        string $headline,
        array $bodyLines,
        ?Order $order = null,
    ): void {
        if (! is_string($email) || $email === '' || ! config('mail.default')) {
            return;
        }

        $bodyText = implode(' ', $bodyLines);
        $actionUrl = $order ? $this->orderUrl($order) : null;

        try {
            Mail::to($email)->send(new CustomerMessage(
                subjectLine: $subject,
                bodyText: $bodyText,
                headline: $headline,
                bodyLines: $bodyLines,
                orderNumber: $order?->order_number,
                actionUrl: $actionUrl,
                actionLabel: $actionUrl ? 'View order' : null,
            ));
        } catch (\Throwable $exception) {
            Log::warning('Customer email could not be sent', [
                'mailable' => CustomerMessage::class,
                'exception' => $exception::class,
            ]);
        }
    }

    protected function orderUrl(Order $order): string
    {
        return url('/orders/'.$order->id);
    }
}
