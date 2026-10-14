const defaultSteps = [
  { key: 'details', label: 'Details' },
  { key: 'delivery', label: 'Delivery' },
  { key: 'payment', label: 'Payment' },
  { key: 'confirmation', label: 'Confirmation' },
] as const;

export type CheckoutStepState = 'completed' | 'current' | 'upcoming';

export type CheckoutStep = {
  key: string;
  label: string;
  state: CheckoutStepState;
};

export function CheckoutProgress({
  current = 'details',
  steps,
}: {
  current?: string;
  steps?: CheckoutStep[];
}) {
  const resolved =
    steps && steps.length > 0
      ? steps
      : defaultSteps.map((step) => ({
          key: step.key,
          label: step.label,
          state: (step.key === current ? 'current' : 'upcoming') as CheckoutStepState,
        }));

  return (
    <ol className="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-4 sm:gap-2" aria-label="Checkout progress">
      {resolved.map((step, index) => {
        const isCurrent = step.state === 'current';
        const isCompleted = step.state === 'completed';

        return (
          <li
            key={step.key}
            className={isCurrent ? 'text-amber-300' : isCompleted ? 'text-emerald-300' : 'text-slate-500'}
            aria-current={isCurrent ? 'step' : undefined}
          >
            <div className="flex items-center gap-2 sm:block sm:text-center">
              <span
                className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full border text-xs font-semibold ${
                  isCurrent
                    ? 'border-amber-400 bg-amber-400/10'
                    : isCompleted
                      ? 'border-emerald-400 bg-emerald-400/10'
                      : 'border-slate-700'
                }`}
              >
                {isCompleted ? <span aria-hidden="true">✓</span> : <span>{index + 1}</span>}
              </span>
              <span className="text-xs font-semibold sm:mt-1 sm:block sm:text-sm">{step.label}</span>
            </div>
            <span className="sr-only">{isCompleted ? 'Completed' : isCurrent ? 'Current step' : 'Upcoming'}</span>
          </li>
        );
      })}
    </ol>
  );
}
