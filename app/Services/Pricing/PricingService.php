<?php

declare(strict_types=1);

namespace App\Services\Pricing;

use App\Exceptions\InvalidPriceException;
use App\Models\Product;
use App\Models\ProductVariant;

class PricingService
{
    /**
     * Safely convert decimal string/number to integer minor currency units (pence) without float imprecision.
     */
    public function toPence(string|float|int $amount): int
    {
        $stringVal = trim((string) $amount);

        if ($stringVal === '' || ! is_numeric($stringVal)) {
            throw new InvalidPriceException("Invalid non-numeric monetary value: '{$stringVal}'.");
        }

        // Use bcmath if available, otherwise strict string-based integer conversion
        if (function_exists('bcmul')) {
            $penceStr = bcmul($stringVal, '100', 0);
            return (int) $penceStr;
        }

        $parts = explode('.', $stringVal);
        $whole = (int) ($parts[0] ?? 0);
        $fraction = substr(($parts[1] ?? '') . '00', 0, 2);

        return ($whole * 100) + (int) $fraction;
    }

    /**
     * Safely convert integer pence to decimal string with 2 decimal places.
     */
    public function toDecimalString(int $pence): string
    {
        $negative = $pence < 0;
        $abs = abs($pence);
        $whole = intdiv($abs, 100);
        $fraction = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return ($negative ? '-' : '') . "{$whole}.{$fraction}";
    }

    /**
     * Calculate authoritative product pricing.
     *
     * @return array{
     *     base_price_amount: string,
     *     base_price_pence: int,
     *     sale_price_amount: ?string,
     *     sale_price_pence: ?int,
     *     effective_price_amount: string,
     *     effective_price_pence: int,
     *     is_on_sale: bool,
     *     currency: string
     * }
     */
    public function calculateProductPrice(Product $product, ?ProductVariant $variant = null): array
    {
        $currency = $product->currency ?? 'GBP';
        $priceSource = $variant ?: $product;

        $rawPrice = (string) $priceSource->price_amount;
        $rawCompareAt = $priceSource->compare_at_price_amount !== null
            ? (string) $priceSource->compare_at_price_amount
            : null;

        $pricePence = $this->toPence($rawPrice);

        if ($pricePence <= 0) {
            throw new InvalidPriceException("Product '{$product->name}' has disallowed zero or negative price ({$rawPrice}).");
        }

        $isOnSale = false;
        $salePricePence = null;
        $salePriceAmount = null;
        $basePricePence = $pricePence;

        if ($rawCompareAt !== null) {
            $compareAtPence = $this->toPence($rawCompareAt);

            if ($compareAtPence < 0) {
                throw new InvalidPriceException("Invalid negative compare_at price: '{$rawCompareAt}'.");
            }

            if ($compareAtPence > 0 && $compareAtPence < $pricePence) {
                throw new InvalidPriceException(
                    "Invalid sale configuration: compare_at price ({$rawCompareAt}) cannot be lower than effective price ({$rawPrice})."
                );
            }

            if ($compareAtPence > $pricePence) {
                $isOnSale = true;
                $basePricePence = $compareAtPence;
                $salePricePence = $pricePence;
                $salePriceAmount = $this->toDecimalString($pricePence);
            }
        }

        $effectivePricePence = $pricePence;

        return [
            'base_price_amount' => $this->toDecimalString($basePricePence),
            'base_price_pence' => $basePricePence,
            'sale_price_amount' => $salePriceAmount,
            'sale_price_pence' => $salePricePence,
            'effective_price_amount' => $this->toDecimalString($effectivePricePence),
            'effective_price_pence' => $effectivePricePence,
            'is_on_sale' => $isOnSale,
            'currency' => $currency,
        ];
    }

    /**
     * Calculate line item totals safely in pence.
     *
     * @return array{
     *     unit_price_amount: string,
     *     unit_price_pence: int,
     *     quantity: int,
     *     line_subtotal_amount: string,
     *     line_subtotal_pence: int,
     *     discount_amount: string,
     *     discount_pence: int,
     *     line_total_amount: string,
     *     line_total_pence: int
     * }
     */
    public function calculateLineItem(
        Product $product,
        ?ProductVariant $variant = null,
        int $quantity = 1,
        string|float|int $discount = 0
    ): array {
        if ($quantity <= 0) {
            throw new InvalidPriceException("Quantity must be strictly positive (received: {$quantity}).");
        }

        $priceInfo = $this->calculateProductPrice($product, $variant);
        $unitPricePence = $priceInfo['effective_price_pence'];

        $subtotalPence = $unitPricePence * $quantity;

        $discountPence = $this->toPence($discount);
        if ($discountPence < 0) {
            throw new InvalidPriceException("Discount cannot be negative (received: {$discount}).");
        }

        // Boundary constraint: discount cannot exceed subtotal
        if ($discountPence > $subtotalPence) {
            $discountPence = $subtotalPence;
        }

        $lineTotalPence = $subtotalPence - $discountPence;

        return [
            'unit_price_amount' => $this->toDecimalString($unitPricePence),
            'unit_price_pence' => $unitPricePence,
            'quantity' => $quantity,
            'line_subtotal_amount' => $this->toDecimalString($subtotalPence),
            'line_subtotal_pence' => $subtotalPence,
            'discount_amount' => $this->toDecimalString($discountPence),
            'discount_pence' => $discountPence,
            'line_total_amount' => $this->toDecimalString($lineTotalPence),
            'line_total_pence' => $lineTotalPence,
        ];
    }

    /**
     * Calculate grand totals for an order/cart.
     *
     * @param iterable<array{product: Product, variant?: ?ProductVariant, quantity: int, discount?: string|float|int}> $items
     * @return array{
     *     subtotal_amount: string,
     *     subtotal_pence: int,
     *     discount_amount: string,
     *     discount_pence: int,
     *     shipping_amount: string,
     *     shipping_pence: int,
     *     tax_amount: string,
     *     tax_pence: int,
     *     grand_total_amount: string,
     *     grand_total_pence: int,
     *     items_count: int,
     *     currency: string
     * }
     */
    public function calculateTotals(
        iterable $items,
        string|float|int $shippingAmount = 0,
        string|float|int $orderDiscount = 0,
        string|float|int $taxAmount = 0,
        string $currency = 'GBP'
    ): array {
        $subtotalPence = 0;
        $itemDiscountsPence = 0;
        $totalItemsCount = 0;

        foreach ($items as $item) {
            /** @var Product $product */
            $product = $item['product'];
            $variant = $item['variant'] ?? null;
            $quantity = (int) $item['quantity'];
            $itemDiscount = $item['discount'] ?? 0;

            $line = $this->calculateLineItem($product, $variant, $quantity, $itemDiscount);

            $subtotalPence += $line['line_subtotal_pence'];
            $itemDiscountsPence += $line['discount_pence'];
            $totalItemsCount += $quantity;
        }

        $orderDiscountPence = $this->toPence($orderDiscount);
        if ($orderDiscountPence < 0) {
            throw new InvalidPriceException('Order discount cannot be negative.');
        }

        $totalDiscountPence = $itemDiscountsPence + $orderDiscountPence;
        if ($totalDiscountPence > $subtotalPence) {
            $totalDiscountPence = $subtotalPence;
        }

        $shippingPence = $this->toPence($shippingAmount);
        if ($shippingPence < 0) {
            throw new InvalidPriceException('Shipping amount cannot be negative.');
        }

        $taxPence = $this->toPence($taxAmount);
        if ($taxPence < 0) {
            throw new InvalidPriceException('Tax amount cannot be negative.');
        }

        $grandTotalPence = ($subtotalPence - $totalDiscountPence) + $shippingPence + $taxPence;

        return [
            'subtotal_amount' => $this->toDecimalString($subtotalPence),
            'subtotal_pence' => $subtotalPence,
            'discount_amount' => $this->toDecimalString($totalDiscountPence),
            'discount_pence' => $totalDiscountPence,
            'shipping_amount' => $this->toDecimalString($shippingPence),
            'shipping_pence' => $shippingPence,
            'tax_amount' => $this->toDecimalString($taxPence),
            'tax_pence' => $taxPence,
            'grand_total_amount' => $this->toDecimalString($grandTotalPence),
            'grand_total_pence' => $grandTotalPence,
            'items_count' => $totalItemsCount,
            'currency' => $currency,
        ];
    }
}
