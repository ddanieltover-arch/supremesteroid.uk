# Vue

## Tooling choice

- **Built-in `<Transition>` / `<TransitionGroup>`** — Vue's native mechanism for enter/leave animation; this is the default for mount/unmount transitions, list reordering, and route transitions. Prefer it before reaching for a library — it covers a large share of what people install a dependency for.
- **CSS transitions/animations** — for hover states and anything not tied to element mount/unmount.
- **GSAP** — for complex sequencing/scroll-scrubbed work; framework-agnostic, integrates cleanly with Vue via refs.
- **Motion for Vue (VueUse Motion or `@vueuse/motion`)** — a Framer-Motion-like declarative API for Vue if the project wants that style of orchestration.
- **VueUse** (`@vueuse/core`) — worth checking for existing composables (`useIntersectionObserver`, `useMediaQuery` for reduced-motion, `useMouse` for parallax) before writing custom logic; it's a very common dependency in Vue projects and duplicating its utilities is wasted effort.

## Nuxt-specific notes

- Nuxt does SSR similarly to Next.js — guard any `window`/`document`-dependent code with `process.client` checks or the `<ClientOnly>` component.
- Nuxt has built-in page transition support via `definePageMeta({ pageTransition: {...} })` — use this instead of hand-rolling route transition logic.

## Example: scroll reveal with `<Transition>` + Intersection Observer

```vue
<script setup>
import { ref } from 'vue'
import { useIntersectionObserver } from '@vueuse/core'

const target = ref(null)
const isVisible = ref(false)
useIntersectionObserver(target, ([entry]) => {
  if (entry.isIntersecting) isVisible.value = true
}, { threshold: 0.2 })
</script>

<template>
  <div ref="target">
    <Transition name="fade-up">
      <div v-if="isVisible" key="content">
        <slot />
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.fade-up-enter-active { transition: opacity 0.5s ease, transform 0.5s ease; }
.fade-up-enter-from { opacity: 0; transform: translateY(24px); }
</style>
```

## Reduced motion

```js
import { useMediaQuery } from '@vueuse/core'
const prefersReducedMotion = useMediaQuery('(prefers-reduced-motion: reduce)')
```
Use this to shorten/skip transition durations rather than hardcoding a fixed duration everywhere.
