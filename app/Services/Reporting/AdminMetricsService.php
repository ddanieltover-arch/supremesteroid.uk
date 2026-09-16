<?php

declare(strict_types=1);

namespace App\Services\Reporting;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\ProductStatus;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentSubmission;
use App\Models\Product;

class AdminMetricsService
{
    /**
     * @return array{
     *     total_orders: int,
     *     orders_today: int,
     *     pending_payments: int,
     *     payments_under_review: int,
     *     paid_awaiting_fulfillment: int,
     *     low_stock_count: int,
     *     out_of_stock_count: int,
     *     compliance_review_backlog: int,
     *     revenue_paid_amount: string,
     *     recent_payment_submissions: list<array<string, mixed>>
     * }
     */
    public function getMetrics(): array
    {
        $totalOrders = Order::query()->count();

        $ordersToday = Order::query()
            ->whereDate('created_at', now()->toDateString())
            ->count();

        $pendingPayments = Payment::query()
            ->where('status', PaymentStatus::PENDING)
            ->count();

        $paymentsUnderReview = Payment::query()
            ->whereIn('status', [PaymentStatus::PAYMENT_SUBMITTED, PaymentStatus::UNDER_REVIEW])
            ->count();

        $paidAwaitingFulfillment = Order::query()
            ->whereIn('status', [OrderStatus::PAID, OrderStatus::PROCESSING])
            ->count();

        $lowStockCount = Inventory::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= safety_stock_threshold')
            ->whereRaw('(quantity_on_hand - quantity_reserved) > 0')
            ->count();

        $outOfStockCount = Inventory::query()
            ->whereRaw('(quantity_on_hand - quantity_reserved) <= 0')
            ->count();

        $complianceReviewBacklog = Product::query()
            ->where(function ($q) {
                $q->whereNull('compliance_verified_at')
                    ->orWhereIn('status', [ProductStatus::UNDER_REVIEW, ProductStatus::COMPLIANCE_REVIEW]);
            })
            ->count();

        $revenuePence = (int) round(((float) Payment::query()
            ->where('status', PaymentStatus::PAID)
            ->sum('amount')) * 100);

        $recentSubmissions = PaymentSubmission::query()
            ->with(['payment.order'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(function (PaymentSubmission $submission) {
                return [
                    'id' => $submission->id,
                    'order_number' => $submission->payment?->order?->order_number,
                    'method' => $submission->submission_type,
                    'amount' => $submission->submitted_amount,
                    'status' => $submission->review_status?->value ?? (string) $submission->review_status,
                    'created_at' => $submission->created_at?->toIso8601String(),
                ];
            })
            ->all();

        return [
            'total_orders' => $totalOrders,
            'orders_today' => $ordersToday,
            'pending_payments' => $pendingPayments,
            'payments_under_review' => $paymentsUnderReview,
            'paid_awaiting_fulfillment' => $paidAwaitingFulfillment,
            'low_stock_count' => $lowStockCount,
            'out_of_stock_count' => $outOfStockCount,
            'compliance_review_backlog' => $complianceReviewBacklog,
            'revenue_paid_amount' => number_format($revenuePence / 100, 2, '.', ''),
            'recent_payment_submissions' => $recentSubmissions,
        ];
    }
}
