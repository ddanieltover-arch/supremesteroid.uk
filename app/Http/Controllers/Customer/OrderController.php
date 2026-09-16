<?php

declare(strict_types=1);

namespace App\Http\Controllers\Customer;

use App\Actions\Payment\SubmitBankTransferPaymentAction;
use App\Actions\Payment\SubmitCryptoPaymentAction;
use App\Http\Requests\SubmitBankTransferProofRequest;
use App\Http\Requests\SubmitCryptoPaymentProofRequest;
use App\Http\Resources\OrderDetailResource;
use App\Http\Resources\OrderSummaryResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Storage\ObjectStorageService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    use AuthorizesRequests;

    public function __construct(
        protected SubmitBankTransferPaymentAction $submitBankTransferPaymentAction,
        protected SubmitCryptoPaymentAction $submitCryptoPaymentAction,
        protected ObjectStorageService $objectStorageService
    ) {}

    public function index(Request $request): Response
    {
        $orders = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'latestPayment'])
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->through(fn (Order $order) => (new OrderSummaryResource($order))->resolve());

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        $order = Order::query()
            ->where('user_id', $request->user()->id)
            ->with(['items', 'payments.submissions.proofFile', 'statusHistory'])
            ->findOrFail($id);

        $this->authorize('view', $order);

        return Inertia::render('Orders/Show', [
            'order' => (new OrderDetailResource($order))->resolve(),
            'paymentInstructions' => [
                'bank_transfer' => array_filter(config('payments.bank_transfer', [])),
                'crypto' => config('payments.crypto', ['networks' => [], 'reference_hint' => null]),
            ],
        ]);
    }

    public function submitBankTransferProof(
        SubmitBankTransferProofRequest $request,
        string $orderId,
        string $paymentId
    ): RedirectResponse {
        [$order, $payment] = $this->authorizePaymentSubmission($request, $orderId, $paymentId);
        $payload = $request->safe()->except(['proof_file']);

        if ($request->hasFile('proof_file')) {
            $stored = $this->objectStorageService->storeUploadedFile(
                $request->file('proof_file'),
                (string) config('filesystems.uploads.payment_proofs_collection', 'payment_proofs'),
                $request->user(),
                $payment
            );
            $payload['proof_file_id'] = $stored->id;
        }

        $this->submitBankTransferPaymentAction->execute($payment, $request->user(), $payload);

        return redirect()
            ->route('orders.show', $order->id)
            ->with('success', 'Payment submission received. Our team will verify and approve your order.');
    }

    public function submitCryptoProof(
        SubmitCryptoPaymentProofRequest $request,
        string $orderId,
        string $paymentId
    ): RedirectResponse {
        [$order, $payment] = $this->authorizePaymentSubmission($request, $orderId, $paymentId);
        $this->submitCryptoPaymentAction->execute($payment, $request->user(), $request->validated());

        return redirect()
            ->route('orders.show', $order->id)
            ->with('success', 'Payment submission received. Our team will verify and approve your order.');
    }

    public function submitPaymentProof(Request $request, string $orderId, string $paymentId): RedirectResponse
    {
        [$order, $payment] = $this->authorizePaymentSubmission($request, $orderId, $paymentId);

        if ($payment->method->value === 'BANK_TRANSFER') {
            $formRequest = SubmitBankTransferProofRequest::createFrom($request);
            $formRequest->setContainer(app())->setRedirector(app('redirect'));
            $formRequest->validateResolved();
            $payload = $formRequest->safe()->except(['proof_file']);

            if ($request->hasFile('proof_file')) {
                $stored = $this->objectStorageService->storeUploadedFile(
                    $request->file('proof_file'),
                    (string) config('filesystems.uploads.payment_proofs_collection', 'payment_proofs'),
                    $request->user(),
                    $payment
                );
                $payload['proof_file_id'] = $stored->id;
            }

            $this->submitBankTransferPaymentAction->execute($payment, $request->user(), $payload);
        } else {
            $validated = $request->validate((new SubmitCryptoPaymentProofRequest())->rules());
            $this->submitCryptoPaymentAction->execute($payment, $request->user(), $validated);
        }

        return redirect()
            ->route('orders.show', $order->id)
            ->with('success', 'Payment submission received. Our team will verify and approve your order.');
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    protected function authorizePaymentSubmission(Request $request, string $orderId, string $paymentId): array
    {
        $order = Order::query()->where('user_id', $request->user()->id)->findOrFail($orderId);
        $payment = Payment::query()->where('order_id', $order->id)->findOrFail($paymentId);

        $this->authorize('submitPaymentProof', $order);
        $this->authorize('submitProof', $payment);

        return [$order, $payment];
    }

    public function downloadPaymentProof(Request $request, string $orderId, string $paymentId): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $order = Order::query()->findOrFail($orderId);
        $payment = Payment::query()
            ->where('order_id', $order->id)
            ->with('submissions.proofFile')
            ->findOrFail($paymentId);

        $this->authorize('view', $payment);

        $file = $payment->submissions
            ->sortByDesc('created_at')
            ->first(fn ($submission) => $submission->proof_file_id)?->proofFile;

        abort_unless($file, 404);

        return $this->objectStorageService->download($file);
    }
}
