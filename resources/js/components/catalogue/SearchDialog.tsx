import { router, useForm } from '@inertiajs/react';
import { useEffect, useId, useState } from 'react';
import { flattenSuggestions, SearchSuggestions } from '@/components/navigation/SearchSuggestions';
import { Dialog } from '@/components/ui/Dialog';
import { Skeleton } from '@/components/ui/Skeleton';
import type { SuggestResponse } from '@/types';

export function SearchDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
  const searchForm = useForm({ q: '' });
  const [suggestions, setSuggestions] = useState<SuggestResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const listId = useId();
  const items = flattenSuggestions(suggestions);
  const activeId = activeIndex >= 0 ? items[activeIndex]?.id : null;

  useEffect(() => {
    const term = searchForm.data.q.trim();
    if (!open || term.length < 2) {
      setSuggestions(null);
      setLoading(false);
      setFailed(false);
      return;
    }
    setLoading(true);
    const handle = window.setTimeout(() => {
      void fetch(`/search/suggest?q=${encodeURIComponent(term)}`)
        .then((response) => {
          if (!response.ok) {
            throw new Error('Search failed');
          }
          return response.json();
        })
        .then((payload: SuggestResponse) => {
          setSuggestions(payload);
          setFailed(false);
          setActiveIndex(-1);
        })
        .catch(() => {
          setSuggestions(null);
          setFailed(true);
        })
        .finally(() => setLoading(false));
    }, 200);
    return () => window.clearTimeout(handle);
  }, [open, searchForm.data.q]);

  return (
    <Dialog open={open} onClose={onClose} title="Search the catalogue" size="full">
      <form
        onSubmit={(event) => {
          event.preventDefault();
          const target = activeIndex >= 0 ? items[activeIndex] : null;
          if (target) {
            router.get(target.url);
          } else {
            searchForm.get('/search');
          }
          onClose();
        }}
      >
        <label className="sr-only" htmlFor="mobile-search">
          Search products
        </label>
        <input
          id="mobile-search"
          role="combobox"
          aria-expanded={Boolean(suggestions || failed || loading)}
          aria-controls={listId}
          aria-autocomplete="list"
          aria-activedescendant={activeId ?? undefined}
          value={searchForm.data.q}
          onChange={(event) => searchForm.setData('q', event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'ArrowDown') {
              event.preventDefault();
              setActiveIndex((index) => Math.min(index + 1, items.length - 1));
            }
            if (event.key === 'ArrowUp') {
              event.preventDefault();
              setActiveIndex((index) => Math.max(index - 1, 0));
            }
          }}
          placeholder="Search products, brands, or categories"
          autoComplete="off"
          className="field"
        />
      </form>

      <div className="relative mt-4 space-y-3" aria-live="polite">
        {loading ? (
          <div className="space-y-2">
            <Skeleton className="h-10 w-full" />
            <Skeleton className="h-10 w-5/6" />
            <Skeleton className="h-10 w-2/3" />
          </div>
        ) : null}
        {failed ? <p className="text-sm text-rose-600">Search is unavailable right now. Try again shortly.</p> : null}
        {!loading && suggestions && items.length === 0 && searchForm.data.q.trim().length >= 2 ? (
          <p className="text-sm text-slate-500">No matching products, brands, or categories.</p>
        ) : null}
        {!loading && suggestions && items.length > 0 ? (
          <SearchSuggestions suggestions={suggestions} query={searchForm.data.q} activeId={activeId} listId={listId} onSelect={onClose} />
        ) : null}
      </div>
    </Dialog>
  );
}
