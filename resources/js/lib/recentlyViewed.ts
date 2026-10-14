const STORAGE_KEY = 'ss.recently-viewed';
const LIMIT = 8;

export function readRecentlyViewed(): string[] {
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

export function rememberRecentlyViewed(slug: string): string[] {
  const next = [slug, ...readRecentlyViewed().filter((item) => item !== slug)].slice(0, LIMIT);
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  window.dispatchEvent(new Event('ss:recently-viewed'));
  return next;
}
