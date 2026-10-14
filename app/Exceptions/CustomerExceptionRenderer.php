<?php

declare(strict_types=1);

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class CustomerExceptionRenderer
{
    public function render(Throwable $e, Request $request): ?Response
    {
        if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
            return null;
        }

        $api = $request->expectsJson() || $request->is('internal/*') || $request->is('api/*');

        if ($e instanceof NotFoundHttpException || $e instanceof ModelNotFoundException) {
            return $this->page($request, 'Errors/NotFound', 404, 'The requested page was not found.', 'NOT_FOUND', $api);
        }

        if ($e instanceof TokenMismatchException) {
            return $this->page($request, 'Errors/Expired', 419, 'Your session expired. Please refresh and try again.', 'SESSION_EXPIRED', $api);
        }

        $payload = $this->payloadFor($e);

        if ($payload !== null && $this->isCheckoutStyle($e)) {
            try {
                Log::warning('Checkout pipeline exception', [
                    'code' => $payload['code'],
                    'exception' => $e::class,
                ]);
            } catch (Throwable) {
                //
            }

            if ($api) {
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

        if ($api) {
            return null;
        }

        if ($e instanceof AuthorizationException) {
            return $this->page($request, 'Errors/Forbidden', 403, 'You are not allowed to perform that action.', 'UNAUTHORIZED', false);
        }

        $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : null;

        return match ($status) {
            403 => $this->page($request, 'Errors/Forbidden', 403, 'You are not allowed to view that page.', 'FORBIDDEN', false),
            419 => $this->page($request, 'Errors/Expired', 419, 'Your session expired. Please refresh and try again.', 'SESSION_EXPIRED', false),
            422 => $this->page($request, 'Errors/Unprocessable', 422, 'We could not process that request. Please review the details and try again.', 'UNPROCESSABLE', false),
            429 => $this->page($request, 'Errors/TooManyRequests', 429, 'Too many requests. Please wait a moment and try again.', 'TOO_MANY_REQUESTS', false),
            503 => $this->page($request, 'Errors/ServiceUnavailable', 503, 'The store is temporarily unavailable. Please try again shortly.', 'SERVICE_UNAVAILABLE', false),
            default => $status >= 500 || $status === null
                ? $this->page(
                    $request,
                    'Errors/ServerError',
                    $status && $status >= 500 ? $status : 500,
                    'The store is experiencing a temporary service issue. Please try again shortly.',
                    'SERVER_ERROR',
                    false
                )
                : null,
        };
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

    protected function isCheckoutStyle(Throwable $e): bool
    {
        return $e instanceof CustomerFacingException
            || $e instanceof ProductNotPurchasableException
            || $e instanceof InsufficientInventoryException
            || $e instanceof InvalidPriceException
            || $e instanceof InvalidPaymentTransitionException;
    }

    protected function seoTitle(string $component): string
    {
        return match ($component) {
            'Errors/NotFound' => 'Page not found',
            'Errors/Forbidden' => 'Not allowed',
            'Errors/Expired' => 'Session expired',
            'Errors/Unprocessable' => 'Could not process request',
            'Errors/TooManyRequests' => 'Too many requests',
            'Errors/ServiceUnavailable' => 'Store unavailable',
            'Errors/ServerError' => 'Temporary interruption',
            default => 'Store notice',
        };
    }

    protected function page(Request $request, string $component, int $status, string $message, string $code, bool $json): Response
    {
        if ($json) {
            return response()->json([
                'message' => $message,
                'code' => $code,
            ], $status);
        }

        return Inertia::render($component, [
            'message' => $message,
            'status' => $status,
            'seo' => [
                'title' => $this->seoTitle($component),
                'description' => $message,
                'robots' => 'noindex,nofollow',
            ],
        ])->toResponse($request)->setStatusCode($status);
    }
}
