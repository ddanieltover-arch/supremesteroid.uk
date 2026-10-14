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
    <div className="sticky bottom-0 z-30 border-t border-slate-800 bg-slate-950/95 px-4 py-3 backdrop-blur">
      <div className="mx-auto flex max-w-7xl flex-wrap items-center justify-between gap-3">
        <p className="text-sm text-slate-300">{ids.length} selected for comparison</p>
        <div className="flex gap-2">
          <Button variant="ghost" onClick={() => { clearCompareIds(); setIds([]); }}>
            Clear
          </Button>
          <Link
            href={`/compare?ids=${ids.join(',')}`}
            className="inline-flex min-h-10 items-center rounded-md bg-amber-400 px-4 text-sm font-semibold text-slate-950"
          >
            Compare
          </Link>
        </div>
      </div>
    </div>
  );
}
