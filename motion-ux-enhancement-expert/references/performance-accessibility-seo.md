# Performance, Accessibility & SEO Guardrails

These apply to every change made under this skill, regardless of enhancement level. A visually impressive change that violates one of these is a regression, not an improvement — treat this file as load-bearing, not optional polish-on-polish.

## Performance

- **Animate `transform` and `opacity` only** wherever possible — these run on the GPU compositor and don't trigger layout recalculation. Avoid animating `top`, `left`, `width`, `height`, `margin`, or other layout-affecting properties; they force the browser to reflow on every frame and are the most common cause of janky animation.
- Use `will-change` sparingly and only on elements actually mid-animation — leaving it on permanently wastes GPU memory.
- Prefer CSS animations/transitions over JS-driven animation for simple cases; reach for a JS library only when you need sequencing, physics, or scroll-linked control that CSS can't express cleanly.
- For image-heavy pages, lazy-load offscreen images and reserve their layout space (explicit `width`/`height` or `aspect-ratio`) to prevent layout shift — this is a direct Core Web Vitals (CLS) concern, not just a nice-to-have.
- Heavy dependencies (Three.js, Lottie, large GSAP plugin bundles) should be code-split/lazy-loaded so they don't block initial page load for users who never scroll to the section that needs them.

## Accessibility

- **Respect `prefers-reduced-motion`.** Any non-trivial animation (parallax, autoplay, large-scale motion) needs a reduced-motion variant — either instant state changes or a much smaller/slower version. This isn't a nice-to-have for a subset of users; treat it as a hard requirement on every new animation you add.
- Keep all interactive elements keyboard-navigable — an animated button, custom dropdown, or gesture-based control still needs to work via Tab/Enter/Space and arrow keys where relevant.
- Preserve visible focus states. A redesign that removes the default focus ring needs to replace it with an equally visible custom one — never removed outright.
- Maintain sufficient color contrast, especially after adding glassmorphism, gradients, or overlays — check text against its actual rendered background, not just the base color.
- Don't rely on animation or color alone to convey state (error, success, active) — pair it with text, an icon, or an ARIA attribute so screen reader users get the same information.
- Keep touch targets at least ~44×44px on mobile, even inside compact or decorative components.
- **Native mobile trap:** swapping a semantic widget (`ElevatedButton`, `TouchableOpacity` with proper `accessibilityRole`, etc.) for a raw gesture primitive (`GestureDetector`, bare `Pressable` without a role) to get custom press animation silently drops screen-reader semantics. If you make this swap for animation control, wrap the result in an explicit semantics/accessibility node (`Semantics(button: true, ...)` in Flutter; `accessibilityRole="button"` in React Native) so it still announces correctly.

## SEO / SSR

- Keep semantic HTML (`<button>`, `<nav>`, `<header>`, heading hierarchy) intact — don't replace meaningful elements with generic `<div>`s purely to make styling or animation easier.
- If the framework does SSR/SSG (Next.js, Nuxt, SvelteKit, Astro), make sure animation-on-mount doesn't hide content from the server-rendered HTML or delay its appearance in a way that hurts perceived load or crawlability — content should be present in the DOM immediately, with animation applied as a visual layer on top rather than a gate on the content appearing at all.
- Preserve existing structured data, meta tags, and heading structure when restructuring markup for a new layout.
