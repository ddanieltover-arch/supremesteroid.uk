import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { clearCompareIds, readCompareIds } from '@/lib/compare';

export function CompareBar() {
  const [ids, setIds] = useState<string[]>([]);

  useEffect(() => {
    const sync = (event?: Event) => {
      const detail = event instanceof CustomEvent ? event.detail : null;
      setIds(Array.isArray(detail) ? detail : readCompareIds());
    };
    sync();
    window.addEventListener('ss:compare', sync);
    return () => window.removeEventListener('ss:compare', sync);
  }, []);

  if (ids.length === 0) {
    return null;
  }

  return (
    <div className="sticky bottom-[calc(3.5rem+env(safe-area-inset-bottom))] z-30 border-t border-slate-200 bg-white/95 px-4 py-3 backdrop-blur lg:bottom-0">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-600">{ids.length} selected for comparison</p>
        <div className="flex gap-2">
          <Button variant="ghost" onClick={() => { clearCompareIds(); setIds([]); }}>
            Clear
          </Button>
          <Link
            href={`/compare?ids=${ids.join(',')}`}
            className="inline-flex min-h-10 items-center rounded-md bg-blue-600 px-4 text-sm font-semibold text-white"
          >
            Compare
          </Link>
        </div>
      </div>
    </div>
  );
}
