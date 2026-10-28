import { Link, router } from '@inertiajs/react';
import { UserRound } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

export function AccountMenu({
  user,
}: {
  user?: { name: string; email?: string } | null;
}) {
  const [open, setOpen] = useState(false);
  const wrapRef = useRef<HTMLDivElement>(null);
  const buttonRef = useRef<HTMLButtonElement>(null);
  const menuId = useId();

  useEffect(() => {
    if (!open) {
      return;
    }
    const onKey = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false);
        buttonRef.current?.focus();
      }
    };
    const onPointer = (event: MouseEvent) => {
      if (wrapRef.current && !wrapRef.current.contains(event.target as Node)) {
        setOpen(false);
      }
    };
    document.addEventListener('keydown', onKey);
    document.addEventListener('mousedown', onPointer);
    return () => {
      document.removeEventListener('keydown', onKey);
      document.removeEventListener('mousedown', onPointer);
    };
  }, [open]);

  if (!user) {
    return (
      <div className="relative" ref={wrapRef}>
        <button
          ref={buttonRef}
          type="button"
          className="rounded-lg border border-slate-200 p-2 text-slate-800 hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600"
          aria-label="Account"
          aria-expanded={open}
          aria-haspopup="true"
          aria-controls={menuId}
          onClick={() => setOpen((value) => !value)}
        >
          <UserRound className="h-5 w-5" />
        </button>
        {open ? (
          <div id={menuId} role="menu" className="absolute right-0 z-50 mt-2 w-48 rounded-xl border border-slate-200 bg-white p-2 shadow-2xl">
            <Link href="/login" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
              Sign in
            </Link>
            <Link href="/register" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
              Create account
            </Link>
          </div>
        ) : null}
      </div>
    );
  }

  return (
    <div className="relative" ref={wrapRef}>
      <button
        ref={buttonRef}
        type="button"
        className="rounded-lg border border-slate-200 p-2 text-slate-800 hover:text-blue-600 focus-visible:ring-2 focus-visible:ring-blue-600"
        aria-label="Account menu"
        aria-expanded={open}
        aria-haspopup="true"
        aria-controls={menuId}
        onClick={() => setOpen((value) => !value)}
      >
        <UserRound className="h-5 w-5" />
      </button>
      {open ? (
        <div id={menuId} role="menu" className="absolute right-0 z-50 mt-2 w-56 rounded-xl border border-slate-200 bg-white p-2 shadow-2xl">
          <p className="px-3 py-2 text-xs text-slate-500">{user.name}</p>
          <Link href="/account" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
            My Account
          </Link>
          <Link href="/account/orders" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
            Orders
          </Link>
          <Link href="/account/wishlist" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
            Wishlist
          </Link>
          <Link href="/account/addresses" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
            Addresses
          </Link>
          <Link href="/account#profile" role="menuitem" className="block rounded-lg px-3 py-2 text-sm text-slate-800 hover:bg-slate-50" onClick={() => setOpen(false)}>
            Profile
          </Link>
          <button
            type="button"
            role="menuitem"
            className="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-800 hover:bg-slate-50"
            onClick={() => {
              setOpen(false);
              router.post('/logout');
            }}
          >
            Logout
          </button>
        </div>
      ) : null}
    </div>
  );
}
