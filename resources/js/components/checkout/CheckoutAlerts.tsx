import { Link } from '@inertiajs/react';
import { Alert } from '@/components/ui/Alert';

function normalizeMessage(message: string | null | undefined): string {
  return (message ?? '').trim().toLowerCase();
}

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
  const fieldErrors = Object.entries(errors)
    .filter(([key, message]) => key !== 'checkout' && key !== 'message' && typeof message === 'string' && message.trim() !== '')
    .map(([, message]) => message);
  const uniqueFieldErrors = [...new Set(fieldErrors)];

  const noticeMessages = [...warnings, checkoutError]
    .filter((message): message is string => typeof message === 'string' && message.trim() !== '')
    .filter((message, index, all) => all.findIndex((item) => normalizeMessage(item) === normalizeMessage(message)) === index);

  const expired = noticeMessages.some((message) => normalizeMessage(message).includes('expired'));
  const stockIssue = affectedItems.length > 0
    || noticeMessages.some((message) => normalizeMessage(message).includes('no longer available'));

  const visibleNotices = noticeMessages.filter((message) => {
    if (expired && normalizeMessage(message).includes('expired')) {
      return false;
    }
    if (stockIssue && normalizeMessage(message).includes('no longer available')) {
      return false;
    }
    return true;
  });

  return (
    <div className="mt-4 space-y-3">
      {visibleNotices.map((notice) => (
        <Alert key={notice} type="warning">
          {notice}
        </Alert>
      ))}
      {uniqueFieldErrors.length > 0 ? (
        <Alert type="error">
          Please fix the highlighted details before continuing.
          <ul className="mt-2 list-disc pl-5">
            {uniqueFieldErrors.map((message) => (
              <li key={message}>{message}</li>
            ))}
          </ul>
        </Alert>
      ) : null}
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
          Your basket has changed. Totals below already use the latest pricing and availability — you can continue checkout.
        </Alert>
      ) : null}
    </div>
  );
}
