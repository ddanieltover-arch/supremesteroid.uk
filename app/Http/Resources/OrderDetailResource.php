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

        $payments = $order->payments?->map(function ($payment) {
            return [
                'id' => $payment->id,
                'method' => $payment->method?->value,
                'method_label' => $payment->method?->label(),
                'status' => $payment->status?->value,
                'status_label' => $payment->status?->label(),
                'amount_due' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
                'expires_at' => $payment->expires_at?->toIso8601String(),
            ];
        })->values();

        $history = $order->statusHistory?->map(fn ($row) => [
            'previous_status' => $row->previous_status,
            'new_status' => $row->new_status,
            'timestamp' => $row->created_at?->toIso8601String(),
            'note' => $row->notes,
        ])->values();

        return array_merge($summary, [
            'shipping_address' => $order->shipping_address_snapshot,
            'billing_address' => $order->billing_address_snapshot,
            'customer_notes' => $order->customer_notes,
            'payments' => $payments,
            'status_history' => $history,
        ]);
    }
}
