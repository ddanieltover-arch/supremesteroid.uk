# Svelte / SvelteKit & Astro

## Svelte & SvelteKit

- **Built-in transitions** (`transition:`, `in:`/`out:` directives with `fade`, `fly`, `slide`, `scale` from `svelte/transition`) — this is the default and usually sufficient for Level 1–2 work; Svelte's compiler generates efficient CSS/JS for these without adding a runtime dependency.
- **`svelte/animate`** (the `flip` function) — for animating list reordering, e.g. items shifting position after a filter/sort.
- **GSAP** — still the right choice for complex sequencing or scroll-scrubbed work beyond what Svelte's built-ins express well.
- **View Transitions API** — SvelteKit has support for using the native View Transitions API on navigation (via `onNavigate`); check current SvelteKit docs since this API surface has been evolving.
- SvelteKit does SSR — guard `window`/`document` access with `browser` from `$app/environment` rather than assuming client-only code is safe to run at module scope.

### Example

```svelte
<script>
  import { fade, fly } from 'svelte/transition';
  let visible = true;
</script>

{#if visible}
  <div in:fly={{ y: 24, duration: 500 }} out:fade>
    Content
  </div>
{/if}
```

## Astro

- Astro ships content mostly static/server-rendered by default — animation needs to be added either via plain CSS (works everywhere, no hydration needed) or inside an interactive island (a React/Vue/Svelte component with a `client:*` directive) if it needs JS-driven behavior or state.
- Prefer CSS-only animation wherever possible in Astro — it avoids shipping any JS for what's often a content-heavy, performance-sensitive site type (blogs, docs, marketing pages), which is usually the whole point of choosing Astro.
- If a scroll-triggered reveal is needed, a small vanilla-JS `IntersectionObserver` snippet in a `<script>` tag is often lighter than pulling in a whole island framework just for that one effect — reach for an island only if the interaction needs real component state.
- Astro supports the View Transitions API natively via the `<ClientRouter />` (formerly `<ViewTransitions />`) component for page-to-page transitions — this is the idiomatic way to add page transitions in Astro rather than building custom routing logic.
