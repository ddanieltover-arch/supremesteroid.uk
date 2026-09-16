<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CustomerExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?Response
    {
        if ($e instanceof ValidationException) {
            return null;
        }

        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'The requested page was not found.',
                    'code' => 'NOT_FOUND',
                ], 404);
            }

            return Inertia::render('Errors/NotFound')->toResponse($request)->setStatusCode(404);
        }

        $payload = $this->payloadFor($e);

        if ($payload === null) {
            $safe = preg_replace(
                '/(?:postgres|postgresql|mysql|pgsql):\/\/\S+/i',
                '[redacted-url]',
                $e->getMessage()
            ) ?? $e->getMessage();

            if ($request->expectsJson() && ! config('app.debug')) {
                try {
                    Log::error('Unhandled customer-facing failure', [
                        'exception' => $e::class,
                    ]);
                } catch (Throwable) {
                    // A broken log channel must not replace the customer response with an empty 500.
                }

                return response()->json([
                    'message' => $e::class.': '.$safe,
                    'code' => 'SERVER_ERROR',
                ], 500);
            }

            return response($e::class.': '.$safe, 500);
        }

        try {
            Log::warning('Checkout pipeline exception', [
                'code' => $payload['code'],
                'exception' => $e::class,
            ]);
        } catch (Throwable) {
            //
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $payload['message'],
                'code' => $payload['code'],
            ], $payload['status']);
        }

        $redirect = back()
            ->withErrors(['checkout' => $payload['message']])
            ->with('error', $payload['message']);

        if ($e instanceof PriceChangedException) {
            $redirect->with('warning', $payload['message']);
        }

        return $redirect;
    }

    /**
     * @return array{message: string, code: string, status: int}|null
     */
    public function payloadFor(Throwable $e): ?array
    {
        if ($e instanceof CustomerFacingException) {
            return [
                'message' => $e->getCustomerMessage(),
                'code' => $e->getErrorCode(),
                'status' => 422,
            ];
        }

        if ($e instanceof ProductNotPurchasableException) {
            return [
                'message' => $e->getCustomerMessage(),
                'code' => $e->getErrorCode(),
                'status' => 422,
            ];
        }

        if ($e instanceof InsufficientInventoryException) {
            return [
                'message' => $e->getCustomerMessage(),
                'code' => $e->getErrorCode(),
                'status' => 409,
            ];
        }

        if ($e instanceof InvalidPriceException) {
            return [
                'message' => $e->getCustomerMessage(),
                'code' => $e->getErrorCode(),
                'status' => 422,
            ];
        }

        if ($e instanceof AuthorizationException) {
            return [
                'message' => 'You are not allowed to perform that action.',
                'code' => 'UNAUTHORIZED',
                'status' => 403,
            ];
        }

        if ($e instanceof InvalidPaymentTransitionException) {
            return [
                'message' => 'This payment cannot be updated in its current state.',
                'code' => 'INVALID_PAYMENT_STATE',
                'status' => 422,
            ];
        }

        return null;
    }
}
