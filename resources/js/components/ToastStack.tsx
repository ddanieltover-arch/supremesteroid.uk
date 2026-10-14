import { usePage } from '@inertiajs/react';
import { X } from 'lucide-react';
import { useEffect, useState } from 'react';

type Flash = { success?: string | null; error?: string | null; warning?: string | null };
type Toast = { id: number; type: 'success' | 'error' | 'warning'; message: string };

export function ToastStack() {
  const { flash } = usePage<{ flash?: Flash }>().props;
  const [toasts, setToasts] = useState<Toast[]>([]);

  useEffect(() => {
    const next: Toast[] = [];
    if (flash?.success) {
      next.push({ id: Date.now(), type: 'success', message: flash.success });
    }
    if (flash?.error) {
      next.push({ id: Date.now() + 1, type: 'error', message: flash.error });
    }
    if (flash?.warning) {
      next.push({ id: Date.now() + 2, type: 'warning', message: flash.warning });
    }
    if (next.length === 0) {
      return;
    }
    setToasts((current) => [...current, ...next]);
    const handle = window.setTimeout(() => {
      setToasts((current) => current.filter((toast) => !next.some((item) => item.id === toast.id)));
    }, 6000);
    return () => window.clearTimeout(handle);
  }, [flash?.success, flash?.error, flash?.warning]);

  if (toasts.length === 0) {
    return null;
  }

  return (
    <div className="pointer-events-none fixed right-4 top-20 z-[60] flex w-[min(100%-2rem,24rem)] flex-col gap-2" aria-live="polite">
      {toasts.map((toast) => (
        <div
          key={toast.id}
          className={`pointer-events-auto flex items-start gap-3 rounded-xl border px-4 py-3 text-sm shadow-2xl ${
            toast.type === 'success'
              ? 'border-emerald-800/70 bg-emerald-950/90 text-emerald-100'
              : toast.type === 'warning'
                ? 'border-amber-800/70 bg-amber-950/90 text-amber-100'
                : 'border-rose-800/70 bg-rose-950/90 text-rose-100'
          }`}
          role="status"
        >
          <p className="flex-1">{toast.message}</p>
          <button type="button" className="rounded p-1 hover:bg-white/10" aria-label="Dismiss notification" onClick={() => setToasts((current) => current.filter((item) => item.id !== toast.id))}>
            <X className="h-4 w-4" />
          </button>
        </div>
      ))}
    </div>
  );
}
