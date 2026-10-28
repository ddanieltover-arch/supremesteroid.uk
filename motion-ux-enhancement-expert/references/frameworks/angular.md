# Angular

## Tooling choice

- **Angular Animations API** (`@angular/animations`, `trigger`/`state`/`transition`/`animate`) — the idiomatic default for component enter/leave, state-based transitions (e.g. expanded/collapsed), and route transitions. Reach for this before a third-party library; it's already available in most Angular projects and integrates with change detection correctly.
- **CSS transitions** — fine for simple hover/focus states that don't need to hook into Angular's component lifecycle.
- **Angular CDK** — check for `@angular/cdk` in the project; it provides `DragDropModule`, overlay/positioning primitives, and `LayoutModule` (which includes a `BreakpointObserver` useful for responsive behavior) that are worth using instead of hand-rolling equivalents.
- **GSAP** — viable for complex sequencing needs beyond what the Animations API comfortably expresses, same as in other frameworks.

## Notes

- Angular's animation system is disabled by default unless `BrowserAnimationsModule` (or `provideAnimations()` in standalone bootstrapping) is registered — check for this before assuming animations will run.
- For standalone-components projects (modern Angular), animations are provided via `provideAnimations()` in `main.ts`/`app.config.ts` rather than an NgModule import — check which style the project uses before adding boilerplate that doesn't match.
- Prefer `:enter`/`:leave` transition states tied to `*ngIf`/`*ngFor` for list and conditional-content animation, which is the standard Angular pattern and will look familiar to anyone maintaining the codebase later.

## Example: expand/collapse transition

```ts
import { trigger, state, style, transition, animate } from '@angular/animations';

export const expandCollapse = trigger('expandCollapse', [
  state('collapsed', style({ height: '0px', opacity: 0 })),
  state('expanded', style({ height: '*', opacity: 1 })),
  transition('collapsed <=> expanded', animate('250ms ease-in-out')),
]);
```

## Reduced motion

Angular doesn't have a built-in reduced-motion primitive — check `window.matchMedia('(prefers-reduced-motion: reduce)').matches` in a service or component, expose it as a signal/observable, and conditionally shorten or skip `animate()` durations based on it.
