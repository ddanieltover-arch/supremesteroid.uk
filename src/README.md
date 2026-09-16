# Mock SPA deprecation

The standalone React SPA that previously lived in `src/` is **not** the production storefront.

The authoritative customer application is:

- Laravel
- Inertia
- React
- TypeScript
- Tailwind

Entry point: `resources/js/app.tsx`  
Vite plugin: `laravel-vite-plugin`  
Production assets: `public/build`

Do not restore `index.html` / `src/main.tsx` as a competing commerce client.
