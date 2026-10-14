const STORAGE_KEY = 'ss.compare-ids';
const LIMIT = 4;

export function readCompareIds(): string[] {
  if (typeof window === 'undefined') {
    return [];
  }

  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    const parsed = raw ? (JSON.parse(raw) as unknown) : [];
    return Array.isArray(parsed) ? parsed.filter((item): item is string => typeof item === 'string') : [];
  } catch {
    return [];
  }
}

export function toggleCompareId(id: string): string[] {
  const current = readCompareIds();
  const next = current.includes(id)
    ? current.filter((item) => item !== id)
    : [...current, id].slice(0, LIMIT);
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  window.dispatchEvent(new CustomEvent('ss:compare', { detail: next }));
  return next;
}

export function clearCompareIds(): void {
  window.localStorage.removeItem(STORAGE_KEY);
  window.dispatchEvent(new CustomEvent('ss:compare', { detail: [] }));
}
