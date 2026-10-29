function readCookie(name: string): string | null {
  if (typeof document === 'undefined') {
    return null;
  }
  const match = document.cookie.match(new RegExp(`(?:^|; )${name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')}=([^;]*)`));
  return match ? decodeURIComponent(match[1]) : null;
}

export function csrfHeaders(): Record<string, string> {
  const xsrf = readCookie('XSRF-TOKEN');
  return {
    'X-Requested-With': 'XMLHttpRequest',
    Accept: 'application/json',
    ...(xsrf ? { 'X-XSRF-TOKEN': xsrf } : {}),
  };
}

export async function jsonFetch<T>(url: string, init: RequestInit = {}): Promise<T> {
  const headers = new Headers(init.headers);
  const defaults = csrfHeaders();
  Object.entries(defaults).forEach(([key, value]) => {
    if (!headers.has(key)) {
      headers.set(key, value);
    }
  });
  if (init.body && !headers.has('Content-Type')) {
    headers.set('Content-Type', 'application/json');
  }

  const response = await fetch(url, {
    credentials: 'same-origin',
    ...init,
    headers,
  });

  if (!response.ok) {
    let message = 'Request failed.';
    try {
      const payload = (await response.json()) as { message?: string; error?: string };
      message = payload.message || payload.error || message;
    } catch {
      // keep default
    }
    throw new Error(message);
  }

  if (response.status === 204) {
    return undefined as T;
  }

  return (await response.json()) as T;
}
