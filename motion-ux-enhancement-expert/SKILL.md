---
name: motion-ux-enhancement-expert
description: Use this skill whenever a user wants their app, site, or component to feel more polished, alive, modern, or premium — even if they never say the words "animation" or "UX." Trigger on requests like "make this feel more premium," "add some polish," "this looks boring/static/flat," "improve the UX," "make it feel more like Apple/Linear/Stripe," "add animations/transitions/micro-interactions," "improve the hero/nav/buttons/cards/forms/dashboard," "make it more responsive/accessible," or any request to review and elevate the frontend of an existing React, Next.js, Vue, Angular, Svelte, Astro, Flutter, or React Native project. Also use this proactively whenever editing frontend UI code where added polish would clearly help, even if the user's ask was narrowly scoped (e.g. "fix this button" → also notice if it has no hover/press state). Do NOT use for backend, API, database, or business-logic work, and do not use just because a request happens to touch a frontend file — only trigger when visual/interaction quality is actually in scope.
---

# Motion & UX Enhancement Expert

## Mission

Turn functional-but-flat interfaces into experiences that feel intentional — through better motion, layout, responsiveness, accessibility, and performance — **without changing what the product does.**

The job is bigger than "add animations." A page can be transformed just as much by fixing spacing, hierarchy, and typography as by adding motion. Read every request through that wider lens: the user asked for polish, not necessarily for movement.

## The one inviolable rule

**Never modify business logic, data flow, API calls, state shape, routing behavior, or backend code unless the user explicitly asks for it.** Everything in this skill operates on presentation: markup, styles, and the animation/interaction layer wrapped around existing components. If a change to logic seems necessary to enable a visual improvement, stop and ask rather than quietly refactoring behavior.

## Philosophy

> Every interaction should feel intentional — not every element should move.

Restraint is what separates a premium interface from a busy one. A single well-timed transition on the element the user is actually looking at beats ambient motion sprinkled everywhere. Before adding an effect, be able to say *why* it helps the user understand or navigate the interface — "because it looks cool" is not a reason on its own. This is also why the diagnosis step below comes before the enhancement step: know what's actually wrong before reaching for motion as the fix.

## Workflow

Work through these steps in order. Skip nothing on a real codebase task — even a "just add hover effects to my buttons" request benefits from a quick stack check so the code you write actually fits the project.

### 1. Detect the stack

Before writing anything, figure out: framework, CSS approach (Tailwind/CSS Modules/styled-components/plain CSS), existing animation library (if any), whether it's web or mobile, and SSR vs CSR. Look at `package.json`, existing component patterns, and file extensions rather than asking the user to self-report — they often don't know precisely. See `references/detection.md` for what to look for and how to infer the right animation approach from what you find.

### 2. Diagnose before prescribing

Look at the actual UI (code, screenshot, or running app) and ask:
- Is the page visually flat, or is something else actually wrong (spacing, hierarchy, weak typography, unclear CTAs, unbalanced color)?
- Where is the user's attention supposed to go, and does the current design get it there?
- Is there already animation that's excessive, janky, or fighting itself?

Don't jump straight to "add a fade-in." A layout or typography fix is sometimes the higher-leverage move, and piling motion on top of a structurally weak layout just makes the weakness more obvious.

### 3. Pick an enhancement level

Match effort to context — a marketing landing page can support far more spectacle than an internal admin dashboard. See the level table below; when it's ambiguous, ask the user or default to Level 2 (motion) as a safe, broadly-appropriate middle ground.

| Level | Name | What it covers |
|---|---|---|
| 1 | Polish | Hover/focus/active states, subtle transitions, cursor affordances |
| 2 | Motion | Scroll-triggered reveals, page/section transitions, entrance animations |
| 3 | Premium UI | Glassmorphism, advanced layout patterns, immersive/full-bleed sections |
| 4 | Interactive | Parallax, cursor-reactive effects, drag/gesture interactions, light 3D |
| 5 | Luxury | WebGL, Three.js, Rive/Lottie/Spline, cinematic sequenced transitions |

A component-level request ("fix this button") usually only needs Level 1–2 treatment; a full landing-page redesign can justify Level 3+. Don't apply Level 5 techniques to a SaaS dashboard just because they're available.

### 4. Apply targeted improvements

Find the relevant section(s) in `references/patterns.md` for concrete techniques by UI area (hero, navigation, buttons, cards, images, forms, dashboards, mobile, scroll behavior, loading states, empty states, page transitions, typography, backgrounds). Each entry lists the technique options and when to reach for each — pick what fits the diagnosis from step 2, not everything on the list.

### 5. Apply the performance, accessibility, and SEO guardrails

Every change from step 4 must respect `references/performance-accessibility-seo.md` — this covers GPU-friendly animation properties, `prefers-reduced-motion`, keyboard/focus handling, and preserving SSR/semantic HTML/Core Web Vitals. These aren't optional extras; a beautiful animation that tanks Lighthouse or breaks keyboard navigation is a regression, not an improvement.

### 6. Implement with the right tool for the framework

Open the relevant file under `references/frameworks/` for the detected stack (`react-nextjs.md`, `vue.md`, `angular.md`, `svelte-astro.md`, `flutter-react-native.md`) — it covers which animation library fits which situation (CSS-only vs. Framer Motion vs. GSAP vs. native platform animation), plus framework-specific gotchas (e.g. Next.js SSR hydration, React Native's Animated vs. Reanimated).

### 7. Look for reuse opportunities

If you're touching more than one or two components with the same technique (e.g. adding lift-on-hover to every card, or the same entrance animation to every section), stop and build one reusable primitive (a `Card` wrapper, a `useScrollReveal` hook, a shared `motion` variant) instead of repeating inline code. This is especially worth doing in larger codebases — check the existing component structure first so the primitive you add matches how the rest of the project is organized, rather than introducing a competing pattern.

### 8. Summarize what changed and why

Close with a short, plain-language summary: what was enhanced, at what level, and anything you deliberately left alone (and why) — e.g. "left the dashboard tables un-animated since row entrance motion would slow down power users scanning data." This tells the user your restraint was a choice, not an oversight.

## Reference files

- `references/detection.md` — how to identify framework, styling approach, and existing animation tooling from the codebase
- `references/patterns.md` — the enhancement pattern library, organized by UI area (hero, nav, buttons, cards, forms, dashboards, mobile, scroll, loading, empty states, transitions, typography, backgrounds, premium effects)
- `references/performance-accessibility-seo.md` — non-negotiable guardrails for any motion/interaction work
- `references/frameworks/react-nextjs.md` — React & Next.js specifics (Framer Motion, GSAP, SSR/hydration concerns)
- `references/frameworks/vue.md` — Vue specifics (transition components, GSAP, Motion for Vue)
- `references/frameworks/angular.md` — Angular specifics (Angular Animations API, CDK)
- `references/frameworks/svelte-astro.md` — Svelte/SvelteKit and Astro specifics (built-in transitions, view transitions API)
- `references/frameworks/flutter-react-native.md` — Flutter and React Native/Expo specifics (native animation APIs, Reanimated, haptics, gestures)

Read only the reference files relevant to the current task — don't load all of them for a single small change.
