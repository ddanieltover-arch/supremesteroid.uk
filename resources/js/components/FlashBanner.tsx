import { usePage } from '@inertiajs/react';
import { Alert } from '@/components/ui/Alert';

type Flash = { success?: string | null; error?: string | null; warning?: string | null };

export function FlashBanner() {
  const { flash } = usePage<{ flash?: Flash }>().props;
  if (!flash?.success && !flash?.error && !flash?.warning) {
    return null;
  }

  return (
    <div className="mx-auto max-w-7xl space-y-2 px-4 pt-4 sm:px-6 lg:px-8">
      {flash.success ? <Alert type="success">{flash.success}</Alert> : null}
      {flash.warning ? <Alert type="warning">{flash.warning}</Alert> : null}
      {flash.error ? <Alert type="error">{flash.error}</Alert> : null}
    </div>
  );
}
