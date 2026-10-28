# React & Next.js

## Tooling choice

- **CSS transitions/animations** — default for hover states, simple entrances, and anything Level 1–2. No dependency cost.
- **Framer Motion** — best fit for component-level orchestration: entrance/exit animations, `AnimatePresence` for mount/unmount transitions, shared-layout animations (`layoutId`), and scroll-linked effects via `useScroll`/`useTransform`. This is the default reach for most React animation needs beyond plain CSS.
- **GSAP** — better than Framer Motion for complex timeline sequencing, scroll-scrubbed animations (via ScrollTrigger), and when precise easing/timeline control matters more than React-idiomatic API. Common in marketing/award-style sites (Level 4–5).
- **Lenis** — smooth-scroll library, often paired with GSAP ScrollTrigger for premium scroll feel; only add it if the project actually wants that heavier, viscous scroll character — it changes the feel of every scroll interaction, which not every product wants.
- **Three.js / React Three Fiber** — Level 5 only; genuine WebGL 3D work. Heavy — code-split it.

Check `package.json` first; if Framer Motion or GSAP is already a dependency, use it rather than introducing the other.

## Next.js-specific notes

- **App Router Server Components can't use hooks or browser APIs.** Any component using `useState`, `useEffect`, Framer Motion hooks, or IntersectionObserver needs the `"use client"` directive. Keep the client boundary as low in the tree as possible — wrap just the animated piece, not the whole page, to preserve server-rendering benefits for the rest.
- **Hydration mismatches**: anything that reads `window`, viewport size, or `prefers-reduced-motion` must not run during SSR. Guard with a mounted-state check or `useEffect`, and render a stable initial state that matches what the server produced.
- **View Transitions**: Next.js has experimental support for the View Transitions API for page transitions — check current Next.js docs for the flag/API since this area moves quickly; don't assume last year's API shape is current.
- Use `next/image` for any image-related enhancement (lazy load, blur placeholder) — it already implements most of the image best practices in the patterns reference natively; don't hand-roll what it already provides.

## Example: scroll-reveal pattern with Framer Motion

```jsx
"use client";
import { motion } from "framer-motion";

function RevealSection({ children }) {
  return (
    <motion.div
      initial={{ opacity: 0, y: 24 }}
      whileInView={{ opacity: 1, y: 0 }}
      viewport={{ once: true, margin: "-80px" }}
      transition={{ duration: 0.5, ease: "easeOut" }}
    >
      {children}
    </motion.div>
  );
}
```

`viewport={{ once: true }}` avoids re-triggering every time the user scrolls past — important for not fatiguing repeat visitors, per the scroll-animation guidance in the patterns reference.

## Reduced motion in React

```jsx
import { useReducedMotion } from "framer-motion";

const shouldReduceMotion = useReducedMotion();
const transition = shouldReduceMotion
  ? { duration: 0 }
  : { duration: 0.5, ease: "easeOut" };
```
