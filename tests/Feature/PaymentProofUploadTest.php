<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\PaymentMethodType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Order\OrderCreationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesCatalogueFixtures;
use Tests\TestCase;

class PaymentProofUploadTest extends TestCase
{
    use RefreshDatabase;
    use CreatesCatalogueFixtures;

    public function test_customer_can_submit_payment_proof_file_metadata(): void
    {
        Storage::fake('local');
        config(['filesystems.cloud' => 'local', 'filesystems.default' => 'local']);

        $user = $this->makeCustomer();
        $product = $this->makeProduct(['price_amount' => 40.00]);

        $order = app(OrderCreationService::class)->createOrder([
            'customer' => $user,
            'items' => [[
                'product_id' => $product->id,
                'variant_id' => null,
                'quantity' => 1,
            ]],
            'shipping_destination' => [
                'country_code' => 'GB',
                'full_name' => 'Alice Customer',
                'address_line_1' => '1 King Street',
                'city' => 'London',
                'state_county' => 'London',
                'postal_code' => 'W6 9HW',
                'phone' => null,
            ],
            'shipping_method_code' => 'UK_STANDARD',
            'payment_method' => PaymentMethodType::BANK_TRANSFER,
            'idempotency_key' => 'proof-upload-1',
        ]);

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();

        $file = UploadedFile::fake()->create('receipt.pdf', 120, 'application/pdf');

        $this->actingAs($user)->post("/orders/{$order->id}/payments/{$payment->id}/proof/bank", [
            'bank_payment_reference' => 'SS-REF-1',
            'sender_account_name' => 'Alice Customer',
            'submitted_amount' => $payment->amount,
            'transfer_date' => now()->toDateString(),
            'proof_file' => $file,
        ])->assertRedirect();

        $payment->refresh();
        $this->assertSame(PaymentStatus::PAYMENT_SUBMITTED, $payment->status);
        $this->assertNotNull($payment->submissions()->first()?->proof_file_id);
        $this->assertNotSame(PaymentStatus::PAID, $payment->status);
    }
}
