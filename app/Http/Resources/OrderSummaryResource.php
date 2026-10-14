<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $order = $this->resource;
        $payment = $order->latestPayment ?? $order->payments?->first();

        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status?->value,
            'status_label' => $order->status?->label(),
            'customer_name' => $order->customer_name_snapshot,
            'currency' => $order->currency,
            'subtotal_amount' => $order->subtotal_amount,
            'shipping_amount' => $order->shipping_amount,
            'discount_amount' => $order->discount_amount,
            'tax_amount' => $order->tax_amount,
            'total_amount' => $order->total_amount,
            'shipping_method' => $order->shipping_method_name_snapshot,
            'placed_at' => $order->created_at?->toIso8601String(),
            'payment' => $payment ? [
                'id' => $payment->id,
                'method' => $payment->method?->value,
                'status' => $payment->status?->value,
                'status_label' => $this->paymentStatusLabel($payment->status?->value),
                'amount_due' => $payment->amount,
                'currency' => $payment->currency,
                'reference' => $payment->reference,
            ] : null,
            'items' => $order->items?->map(function ($item) {
                $image = $item->product?->relationLoaded('images')
                    ? ($item->product->images->firstWhere('is_primary', true) ?? $item->product->images->first())
                    : null;

                return [
                    'product_id' => $item->product_id,
                    'variant_id' => $item->product_variant_id,
                    'product_name_snapshot' => $item->product_name,
                    'sku_snapshot' => $item->product_sku,
                    'variant_name' => $item->variant_name_snapshot,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'line_subtotal' => $item->line_subtotal,
                    'discount' => $item->discount_amount,
                    'tax' => $item->tax_amount,
                    'line_total' => $item->line_total,
                    'image_url' => $image?->publicUrl() ?? $image?->url,
                ];
            })->values(),
        ];
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
