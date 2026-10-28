import { useEffect, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';

/** Renders children into document.body so fixed overlays escape transform/filter ancestors. */
export function Portal({ children }: { children: ReactNode }) {
  const [target, setTarget] = useState<HTMLElement | null>(null);

  useEffect(() => {
    setTarget(document.body);
  }, []);

  if (!target) {
    return null;
  }

  return createPortal(children, target);
}
