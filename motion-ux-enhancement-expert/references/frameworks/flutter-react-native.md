# Flutter & React Native / Expo

There is no CSS on native — every technique here uses the platform's own animation system. Don't try to port a web pattern literally; translate the *intent* (e.g. "hover glow" on web becomes "press state with scale + opacity" on mobile, since there's no hover on touch).

## Flutter

- **Implicit animations** (`AnimatedContainer`, `AnimatedOpacity`, `AnimatedPositioned`, `AnimatedDefaultTextStyle`) — default choice for simple state-driven transitions (size, color, position, opacity changes). Lowest effort, covers most Level 1–2 needs.
- **Explicit animations** (`AnimationController` + `Tween` + `AnimatedBuilder`) — needed when you require precise control: custom curves, sequencing, repeating/reversing, or coordinating multiple properties together.
- **Hero widget** — for shared-element transitions between screens (e.g. a list item's image expanding into a detail view) — Flutter's native equivalent of the web's shared-element pattern.
- **Rive / Lottie** (`rive` / `lottie` packages) — for complex pre-authored animations (illustrations, loading animations, mascots) built in an external design tool rather than coded by hand.
- Respect the OS-level reduced-motion setting via `MediaQuery.of(context).disableAnimations` — check this before playing non-essential animations and shorten/skip them if true.

## React Native / Expo

- **`Animated` API** (built into React Native) — fine for simple, non-gesture-driven animations, but runs on the JS thread by default and can drop frames under load unless `useNativeDriver: true` is set (only works for transform/opacity — same constraint as web).
- **Reanimated** (`react-native-reanimated`) — the modern default for anything beyond simple fades: gesture-driven animation, worklets running on the UI thread, and smoother performance overall. If the project already has it installed, prefer it over the built-in `Animated` API.
- **`react-native-gesture-handler`** — pairs with Reanimated for swipe, drag, and pan gestures; check if it's already a dependency before reaching for lower-level touch handling.
- **Expo Haptics** (`expo-haptics`) — the standard way to add haptic feedback on supported devices; wrap in a platform check since haptics are iOS/Android-specific and unavailable on web (if the project also targets web via Expo).
- **Lottie** (`lottie-react-native`) — same use case as Flutter: pre-authored complex animations.
- Respect reduced-motion via `AccessibilityInfo.isReduceMotionEnabled()` (async) — check it early (e.g. on mount) and gate non-essential animation on the result.

## Shared principle

On both platforms, native page-transition conventions differ by OS (iOS pushes slide in from the right and can be swiped back; Android has its own default transitions) — use the platform's native navigation transition rather than a custom one unless there's a specific reason to override it, since users' muscle memory expects the native feel.
