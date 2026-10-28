import { useEffect, useRef, type ReactNode } from 'react';
import clsx from 'clsx';

type RevealProps = {
  children: ReactNode;
  className?: string;
  /** Delay before the reveal animation starts once in view (ms). */
  delayMs?: number;
  as?: 'div' | 'section';
};

/**
 * Scroll-triggered reveal. Content stays in the DOM (SSR-safe);
 * animation is a visual layer only. Runs once. Above-fold content
 * stays visible so there is no hydration flash.
 */
export function Reveal({ children, className, delayMs = 0, as: Tag = 'div' }: RevealProps) {
  const ref = useRef<HTMLElement | null>(null);

  useEffect(() => {
    const node = ref.current;
    if (!node) {
      return;
    }

    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if (reduceMotion) {
      node.dataset.reveal = 'visible';
      return;
    }

    const rect = node.getBoundingClientRect();
    const inView = rect.top < window.innerHeight * 0.88 && rect.bottom > 0;
    if (inView) {
      node.dataset.reveal = 'visible';
      return;
    }

    node.dataset.reveal = 'pending';

    const observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) {
            continue;
          }
          window.setTimeout(() => {
            node.dataset.reveal = 'visible';
          }, delayMs);
          observer.unobserve(node);
        }
      },
      { rootMargin: '0px 0px -8% 0px', threshold: 0.1 },
    );

    observer.observe(node);
    return () => observer.disconnect();
  }, [delayMs]);

  return (
    <Tag ref={ref as never} className={clsx('reveal', className)} data-reveal="visible">
      {children}
    </Tag>
  );
}
