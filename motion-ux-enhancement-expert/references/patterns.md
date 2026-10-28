# Enhancement Pattern Library

Organized by UI area. Each area lists techniques with a note on when they earn their place — not every technique in a section belongs in every project. Pick based on the diagnosis, not the whole list.

## Hero section

A hero has one job: communicate what the product is and prompt the first click. Motion should serve that, not compete with it.
- **Animated typography / gradient text** — good for a short, punchy headline; skip if the headline is long or information-dense.
- **Soft gradient/mesh backgrounds, floating shapes, subtle particles** — add depth without stealing focus; keep opacity and motion speed low so text stays the clear foreground.
- **Mouse parallax** — nice on marketing pages with a spacious hero; avoid on content-dense or data-heavy pages where it reads as distracting.
- **Animated CTA button** — a subtle scale/glow on hover reinforces "this is clickable"; don't animate it constantly (idle pulsing trains users to ignore it).
- **Scroll indicator** — worth adding only if there's meaningfully more content below the fold that isn't obvious at a glance.

## Navigation

- **Sticky blur navbar** — frosted background on scroll keeps content legible underneath; common and safe.
- **Scroll shrink** — navbar compresses as the user scrolls down, reclaiming space; good for content-heavy sites.
- **Active section highlighting** — pairs well with single-page scroll sites; skip for traditional multi-page nav.
- **Animated underline / hover effects** — cheap, reliable, low-risk polish for any nav.
- **Mobile slide-in nav + morphing hamburger icon** — standard, expected pattern; don't reinvent it in a way that hides the exit action.
- **Smart hide/show on scroll direction** — reclaims vertical space on mobile; make sure it never hides while a menu is open.

## Buttons

- **Hover glow / scale** — the baseline "this is alive" signal; nearly always worth having on primary CTAs.
- **Ripple effect** — communicates a tap was registered; most valuable on mobile/touch targets.
- **Magnetic hover** (button subtly follows cursor) — a Level 4 flourish; use sparingly, only on a handful of hero-level CTAs, never on every button on a page.
- **Loading / progress / success states** — not decorative — these are functional feedback and should exist on any button that triggers an async action, regardless of enhancement level.

## Cards

- **Lift + shadow on hover** — the default, low-risk way to signal interactivity on clickable cards.
- **Border gradient / glow** — good for a small number of "featured" or "premium" cards; overusing it across a whole grid dilutes the emphasis.
- **Mouse tilt (3D)** — Level 4; effective on a handful of hero/showcase cards, fatiguing across a large grid.
- **Glassmorphism** — works well over a rich background (image, gradient, blur); looks muddy over a plain white/flat background — check the context before applying.
- **Staggered entrance on scroll** — good for card grids appearing on scroll; keep the stagger delay small (40–80ms) so it reads as a wave, not a slow reveal.

## Images

- **Lazy load + fade-in / blur-up placeholder** — near-universal good practice, not just decoration; do this by default on any image-heavy page.
- **Skeleton loaders** — use when image dimensions are known ahead of time, to prevent layout shift (a Core Web Vitals concern, see the performance reference).
- **Zoom on hover** — good for product/gallery imagery; skip on purely informational or icon imagery where it adds noise.

## Forms

- **Floating labels** — clean default for modern forms; make sure the label remains legible against the input background at all states.
- **Inline validation + animated error states** — reduces the "submit and get yelled at" pattern; validate on blur, not on every keystroke, to avoid nagging the user mid-input.
- **Success animation on submit** — confirms the action landed, especially valuable when there's no page navigation afterward.
- **Multi-step form transitions** — slide/fade between steps; always show progress (a step indicator) alongside the transition so the user isn't just trusting the animation to convey where they are.
- **Password strength meter** — functional, not purely aesthetic; only add if the form actually enforces or benefits from strength feedback.

## Dashboards

Dashboards reward restraint more than any other area — the users are trying to extract information quickly.
- **Animated counters / number roll-up** — good for headline KPI numbers, sparingly (2–4 per view, not every number on the page).
- **Loading skeletons matching the final layout shape** — prevents layout shift and reads as "faster" than a spinner.
- **Animated chart entrance** — subtle bar/line draw-in on first load is fine; avoid re-animating on every data refresh, which gets tiring for users who check the dashboard often.
- **Micro-interactions on interactive elements** (filters, toggles, row expand/collapse) — worth it; these get used constantly.
- **Empty states with a clear next action** — see the Empty States section below; especially important in dashboards where "no data yet" is a common first-run state.

## Mobile experience

- **Thumb-reach-aware layout** — put primary actions in the lower two-thirds of the screen where they're easy to reach one-handed.
- **Swipe gestures, pull-to-refresh, bottom sheets** — match platform conventions (iOS vs. Android have different idioms); don't invent a custom gesture for something the OS already has a pattern for.
- **Native-feeling page transitions** (slide for push navigation, not fade) — mismatched transition direction is one of the fastest ways a web app feels non-native.
- **Haptic feedback** — only where the platform API is actually available (React Native/Flutter native builds); there is no web equivalent, don't fake it.

## Scroll animations

Reveal-on-scroll (fade, slide, zoom, stagger) is the highest-value, lowest-risk motion category — it rewards scrolling without demanding attention. Keep individual reveal animations short (300–600ms) and don't reveal the same element twice if the user scrolls back up and down. Full scroll-driven parallax or scrubbed timelines are a heavier (Level 4) commitment — confirm the page's content justifies the complexity before building one.

## Loading experience

Never ship a blank white screen for a known wait. Match the loading treatment to the wait length: skeleton screens for predictable layouts, progressive/streaming content where the framework supports it (e.g. React Suspense, Next.js streaming), and shimmer/blur placeholders for images. A spinner is the least informative option — reach for it last.

## Empty states

Replace bare "No data" text with: a small illustration or icon, one sentence explaining why it's empty, and — if there's an action that would fill it — a clear CTA. This turns a dead end into a next step.

## Page transitions

Fade and slide are safe defaults for most sites. Shared-element transitions (an image or card that visually morphs from list view to detail view) are a strong signature effect but require more implementation care — worth it for a portfolio, gallery, or product page; often overkill for a standard SaaS app. The browser View Transitions API is worth checking for support/fallback needs if targeting it directly.

## Typography

- **Gradient text, split-text reveal, word/character stagger** — high impact on short display headlines; never apply to body copy or paragraphs — it becomes unreadable and slows the user down.
- **Typewriter effect** — use rarely and only for very short strings; it actively delays the user from reading the content, which is a real cost, not just a stylistic choice.

## Backgrounds

Aurora/mesh gradients, noise textures, subtle particle fields, and animated SVG are good low-cost depth. Canvas/WebGL/Three.js backgrounds are a Level 5 commitment — heavier to build, heavier on performance and battery, and should be reserved for pages designed to showcase them (portfolios, product launches) rather than applied by default to an app's every page.

## Premium visual effects

Glassmorphism, neumorphism, "liquid glass," claymorphism, and soft-UI all trade off legibility for texture to different degrees. Check contrast ratios after applying any of these — a frosted panel that looks great in a screenshot can fail accessibility contrast requirements against certain backgrounds. Pick one consistent effect language per project rather than mixing several, which reads as indecisive rather than premium.
