import { router, useForm } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useId, useState } from 'react';
import { flattenSuggestions, SearchSuggestions } from '@/components/navigation/SearchSuggestions';
import type { SuggestResponse } from '@/types';

export function HeaderSearch() {
  const searchForm = useForm({ q: '' });
  const [suggestions, setSuggestions] = useState<SuggestResponse | null>(null);
  const [loading, setLoading] = useState(false);
  const [failed, setFailed] = useState(false);
  const [open, setOpen] = useState(false);
  const [activeIndex, setActiveIndex] = useState(-1);
  const listId = useId();
  const inputId = useId();
  const items = flattenSuggestions(suggestions);
  const activeId = activeIndex >= 0 ? items[activeIndex]?.id : null;

  useEffect(() => {
    const term = searchForm.data.q.trim();
    if (term.length < 2) {
      setSuggestions(null);
      setLoading(false);
      setFailed(false);
      setActiveIndex(-1);
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
          setOpen(true);
          setActiveIndex(-1);
        })
        .catch(() => {
          setSuggestions(null);
          setFailed(true);
          setOpen(true);
        })
        .finally(() => setLoading(false));
    }, 200);
    return () => window.clearTimeout(handle);
  }, [searchForm.data.q]);

  const submit = () => {
    const target = activeIndex >= 0 ? items[activeIndex] : null;
    if (target) {
      setOpen(false);
      router.get(target.url);
      return;
    }
    searchForm.get('/search');
    setOpen(false);
  };

  return (
    <form
      className="relative hidden lg:block"
      onSubmit={(event) => {
        event.preventDefault();
        submit();
      }}
    >
      <label className="sr-only" htmlFor={inputId}>
        Search products
      </label>
      <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" aria-hidden="true" />
      <input
        id={inputId}
        role="combobox"
        aria-expanded={open && Boolean(suggestions || failed || loading)}
        aria-controls={listId}
        aria-autocomplete="list"
        aria-activedescendant={activeId ?? undefined}
        value={searchForm.data.q}
        onChange={(event) => searchForm.setData('q', event.target.value)}
        onFocus={() => setOpen(true)}
        onBlur={() => window.setTimeout(() => setOpen(false), 150)}
        onKeyDown={(event) => {
          if (event.key === 'Escape') {
            setOpen(false);
            setActiveIndex(-1);
            return;
          }
          if (event.key === 'ArrowDown') {
            event.preventDefault();
            setOpen(true);
            setActiveIndex((index) => Math.min(index + 1, items.length - 1));
            return;
          }
          if (event.key === 'ArrowUp') {
            event.preventDefault();
            setActiveIndex((index) => Math.max(index - 1, 0));
          }
        }}
        placeholder="Search catalogue"
        autoComplete="off"
        className="w-72 rounded-lg border border-slate-800 bg-slate-900 py-2 pl-9 pr-3 text-sm text-slate-100 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-400"
      />
      {open && loading ? <p className="absolute z-50 mt-1 w-full rounded-xl border border-slate-800 bg-[#020617] p-3 text-sm text-slate-400">Searching…</p> : null}
      {open && failed ? <p className="absolute z-50 mt-1 w-full rounded-xl border border-slate-800 bg-[#020617] p-3 text-sm text-rose-300">Search is unavailable right now.</p> : null}
      {open && !loading && suggestions && items.length === 0 && searchForm.data.q.trim().length >= 2 ? (
        <p className="absolute z-50 mt-1 w-full rounded-xl border border-slate-800 bg-[#020617] p-3 text-sm text-slate-400">No matching products, brands, or categories.</p>
      ) : null}
      {open && !loading && suggestions && items.length > 0 ? (
        <div className="absolute left-0 right-0">
          <SearchSuggestions suggestions={suggestions} query={searchForm.data.q} activeId={activeId} listId={listId} onSelect={() => setOpen(false)} />
        </div>
      ) : null}
    </form>
  );
}
