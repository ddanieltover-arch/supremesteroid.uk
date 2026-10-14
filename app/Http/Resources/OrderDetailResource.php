<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $summary = (new OrderSummaryResource($order))->toArray($request);

        $payments = $order->payments?->map(function ($payment) use ($order) {
            $latest = $payment->submissions?->sortByDesc('created_at')->first();
            $proofFile = $latest?->proofFile;

            return [
                'id' => $payment->id,
                'method' => $payment->method?->value,
                'method_label' => $payment->method?->label(),
                'status' => $payment->status?->value,
                'status_label' => $this->paymentStatusLabel($payment->status?->value),
                'amount_due' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference ?? $latest?->bank_payment_reference,
                'expires_at' => $payment->expires_at?->toIso8601String(),
                'submitted_at' => $latest?->created_at?->toIso8601String(),
                'proof' => $proofFile ? [
                    'filename' => $proofFile->original_filename,
                    'submitted_at' => $latest?->created_at?->toIso8601String(),
                    'download_url' => route('orders.payment.proof.download', [$order->id, $payment->id]),
                ] : null,
            ];
        })->values();

        $history = $order->statusHistory?->map(fn ($row) => [
            'previous_status' => $row->previous_status,
            'new_status' => $row->new_status,
            'timestamp' => $row->created_at?->toIso8601String(),
            'note' => $row->notes,
        ])->values() ?? collect();

        $attributes = $order->getAttributes();
        $trackingNumber = $attributes['tracking_number'] ?? $attributes['carrier_tracking_number'] ?? null;
        $trackingUrl = $attributes['tracking_url'] ?? $attributes['carrier_tracking_url'] ?? null;
        $hasTracking = filled($trackingNumber) || filled($trackingUrl);

        return array_merge($summary, [
            'shipping_address' => $order->shipping_address_snapshot,
            'billing_address' => $order->billing_address_snapshot,
            'customer_notes' => $order->customer_notes,
            'payments' => $payments,
            'status_history' => $history,
            'timeline' => $this->timeline($order, $history),
            'tracking' => [
                'available' => (bool) $hasTracking,
                'carrier' => $attributes['tracking_carrier'] ?? $attributes['carrier_name'] ?? null,
                'number' => $hasTracking ? $trackingNumber : null,
                'url' => $hasTracking && is_string($trackingUrl) ? $trackingUrl : null,
                'message' => $hasTracking
                    ? null
                    : 'Tracking information will appear here once your order has been dispatched.',
            ],
        ]);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $history
     * @return list<array{key: string, label: string, reached: bool, at: string|null}>
     */
    protected function timeline(mixed $order, $history): array
    {
        $reachedCodes = collect($history)
            ->pluck('new_status')
            ->filter()
            ->push($order->status?->value)
            ->unique()
            ->values();

        $stamps = collect($history)
            ->filter(fn (array $row) => filled($row['new_status'] ?? null))
            ->mapWithKeys(fn (array $row) => [$row['new_status'] => $row['timestamp'] ?? null]);

        $current = $order->status?->value;
        $show = [
            'received' => true,
            'payment_review' => $reachedCodes->contains('PAYMENT_REVIEW'),
            'payment_confirmed' => $reachedCodes->contains('PAID') || in_array($current, ['PAID', 'PROCESSING', 'SHIPPED', 'DELIVERED'], true),
            'processing' => $reachedCodes->contains('PROCESSING') || in_array($current, ['PROCESSING', 'SHIPPED', 'DELIVERED'], true),
            'dispatched' => $reachedCodes->contains('SHIPPED') || in_array($current, ['SHIPPED', 'DELIVERED'], true),
            'delivered' => $reachedCodes->contains('DELIVERED') || $current === 'DELIVERED',
        ];

        $steps = [
            ['key' => 'received', 'label' => 'Order received', 'stamp' => 'PENDING_PAYMENT'],
            ['key' => 'payment_review', 'label' => 'Payment review', 'stamp' => 'PAYMENT_REVIEW'],
            ['key' => 'payment_confirmed', 'label' => 'Payment confirmed', 'stamp' => 'PAID'],
            ['key' => 'processing', 'label' => 'Processing', 'stamp' => 'PROCESSING'],
            ['key' => 'dispatched', 'label' => 'Dispatched', 'stamp' => 'SHIPPED'],
            ['key' => 'delivered', 'label' => 'Delivered', 'stamp' => 'DELIVERED'],
        ];

        $reached = [];
        foreach ($steps as $step) {
            if (! ($show[$step['key']] ?? false)) {
                continue;
            }

            $reached[] = [
                'key' => $step['key'],
                'label' => $step['label'],
                'reached' => true,
                'at' => $stamps->get($step['stamp']) ?? ($step['key'] === 'received' ? $order->created_at?->toIso8601String() : null),
            ];
        }

        return $reached;
    }

    protected function paymentStatusLabel(?string $status): string
    {
        return match ($status) {
            'PENDING' => 'Payment pending',
            'PAYMENT_SUBMITTED' => 'Payment submitted',
            'UNDER_REVIEW' => 'Payment under review',
            'PAID' => 'Payment approved',
            'REJECTED' => 'Payment rejected',
            'EXPIRED' => 'Payment expired',
            'REFUNDED' => 'Refunded',
            default => 'Payment pending',
        };
    }
}
