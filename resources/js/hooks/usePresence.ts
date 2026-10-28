import { useEffect, useState } from 'react';

/**
 * Keeps a portal/overlay mounted through its exit animation.
 * When `open` becomes true: mount immediately, then mark visible on the next frame.
 * When `open` becomes false: mark invisible, unmount after `durationMs`.
 */
export function usePresence(open: boolean, durationMs = 220) {
  const [mounted, setMounted] = useState(open);
  const [visible, setVisible] = useState(open);

  useEffect(() => {
    if (open) {
      setMounted(true);
      const frame = requestAnimationFrame(() => {
        setVisible(true);
      });
      return () => cancelAnimationFrame(frame);
    }

    setVisible(false);
    const timer = window.setTimeout(() => setMounted(false), durationMs);
    return () => window.clearTimeout(timer);
  }, [open, durationMs]);

  return { mounted, visible };
}
