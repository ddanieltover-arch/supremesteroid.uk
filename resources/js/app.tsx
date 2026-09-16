import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType } from 'react';
import '../css/app.css';

createInertiaApp({
  title: (title) => (title ? `${title} | Supreme Steroids` : 'Supreme Steroids'),
  progress: {
    color: '#f59e0b',
    delay: 120,
  },
  resolve: (name) => {
    const pages = import.meta.glob('./Pages/**/*.tsx', { eager: true }) as Record<
      string,
      { default: ComponentType }
    >;
    const page = pages[`./Pages/${name}.tsx`];
    if (!page) {
      throw new Error(`Inertia page not found: ${name}`);
    }
    return page;
  },
  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />);
  },
});
