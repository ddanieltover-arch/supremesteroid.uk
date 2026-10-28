# Stack Detection

Do this before writing any code. The goal is to answer five questions, in order of how much they constrain your choices:

1. **Framework** — React, Next.js, Vue, Angular, Svelte, SvelteKit, Astro, Flutter, React Native/Expo?
2. **Web or mobile?** — changes what's even possible (no CSS on native; haptics only on native).
3. **CSS approach** — Tailwind, CSS Modules, styled-components/Emotion, plain CSS/SCSS, or a component library (MUI, Chakra, shadcn/ui)?
4. **Existing animation tooling** — is Framer Motion, GSAP, Lenis, Locomotive Scroll, Barba, Rive, Lottie, or Three.js already a dependency?
5. **Rendering mode** — SSR (Next.js/Nuxt/SvelteKit/Astro) or CSR (plain Vite/CRA)? This affects hydration-sensitive animation choices.

## Where to look

- `package.json` dependencies/devDependencies — fastest signal for framework, CSS library, and animation library all at once.
- Config files: `next.config.js`, `vite.config.ts`, `angular.json`, `svelte.config.js`, `astro.config.mjs` confirm the framework and its rendering mode.
- File extensions and folder conventions: `.tsx`/`.jsx` → React; `.vue` → Vue; `pages/` or `app/` router → Next.js; `.svelte` → Svelte; `.dart` → Flutter.
- Existing component code: check a couple of components to see the actual patterns in use (class-based CSS vs. utility classes vs. CSS-in-JS) rather than trusting the config alone — real projects drift from their starting template.
- Tailwind: look for `tailwind.config.js`/`.ts` and utility classes in markup.
- Existing motion: `grep`-search for imports like `framer-motion`, `gsap`, `@react-spring`, `lottie-react`, `react-native-reanimated`. If something is already there, prefer extending it over introducing a second animation library — two motion libraries in one project is a maintenance and bundle-size cost that rarely pays for itself.

## Inferring the right approach from what you find

- **Nothing installed, small number of changes needed** → reach for CSS transitions/animations first. No new dependency, no bundle cost, and it's usually enough for Level 1–2 work.
- **Nothing installed, but the project wants Level 3+ (parallax, complex sequencing, physics)** → this is when introducing Framer Motion (React) or GSAP (framework-agnostic) is justified. Say so explicitly and mention the new dependency rather than silently adding it.
- **Framer Motion or GSAP already present** → use it, matching the project's existing conventions (variant naming, timeline structure) instead of introducing a different style.
- **Mobile (Flutter/React Native)** → there's no CSS; use the platform's native animation system (see `frameworks/flutter-react-native.md`). Don't try to port a CSS technique 1:1.
- **SSR framework (Next.js, Nuxt, SvelteKit, Astro)** → double check anything that touches `window`, `document`, or IntersectionObserver is guarded for client-only execution, and that animation-on-mount doesn't cause a hydration flash (see the relevant framework reference file).

Report back what you found in one or two sentences before proceeding — this keeps the diagnosis and enhancement grounded in the actual project rather than a generic assumption.
