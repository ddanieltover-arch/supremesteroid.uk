import { Link, router } from '@inertiajs/react';
import { Alert } from '@/components/ui/Alert';
import { Button } from '@/components/ui/Button';

export function CheckoutAlerts({
  warnings = [],
  errors = {},
  affectedItems = [],
  requiresAcknowledgement = false,
}: {
  warnings?: string[];
  errors?: Record<string, string>;
  affectedItems?: Array<{ name: string; quantity?: number }>;
  requiresAcknowledgement?: boolean;
}) {
  const checkoutError = errors.checkout ?? errors.message;
  const expired = [...warnings, checkoutError].some((message) => message?.toLowerCase().includes('expired'));
  const stockIssue = affectedItems.length > 0 || [...warnings, checkoutError].some((message) => message?.toLowerCase().includes('no longer available'));

  return (
    <div className="mt-4 space-y-3">
      {warnings.map((warning) => (
        <Alert key={warning} type="warning">
          {warning}
        </Alert>
      ))}
      {checkoutError && !warnings.includes(checkoutError) ? <Alert type="warning">{checkoutError}</Alert> : null}
      {stockIssue ? (
        <Alert type="error">
          One item in your basket is no longer available in the selected quantity.
          {affectedItems.length > 0 ? (
            <ul className="mt-2 list-disc pl-5">
              {affectedItems.map((item) => (
                <li key={item.name}>{item.name}</li>
              ))}
            </ul>
          ) : null}
          <Link href="/cart" className="mt-3 inline-block font-semibold text-blue-600">
            Review basket
          </Link>
        </Alert>
      ) : null}
      {expired ? (
        <Alert type="error">
          Your checkout session has expired.
          <Link href="/cart" className="mt-3 inline-block font-semibold text-blue-600">
            Return to basket
          </Link>
        </Alert>
      ) : null}
      {requiresAcknowledgement ? (
        <Alert type="warning">
          Your basket has changed. We refreshed the latest pricing and availability.
          <div className="mt-3">
            <Button type="button" size="sm" onClick={() => router.post('/checkout/acknowledge')}>
              Continue with updated totals
            </Button>
          </div>
        </Alert>
      ) : null}
    </div>
  );
}
