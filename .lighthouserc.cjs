/**
 * Lighthouse CI budget stubs for Supreme Steroids storefront.
 * Set LHCI_BASE_URL (e.g. https://supremesteroid.uk) when running in CI.
 */
const baseUrl = (process.env.LHCI_BASE_URL || 'https://supremesteroid.uk').replace(/\/$/, '');

module.exports = {
  ci: {
    collect: {
      numberOfRuns: 1,
      url: [`${baseUrl}/`, `${baseUrl}/shop`],
      settings: {
        preset: 'desktop',
        onlyCategories: ['performance', 'seo', 'accessibility'],
      },
    },
    assert: {
      assertions: {
        'categories:performance': ['warn', { minScore: 0.7 }],
        'categories:seo': ['error', { minScore: 0.9 }],
        'categories:accessibility': ['warn', { minScore: 0.85 }],
        'largest-contentful-paint': ['warn', { maxNumericValue: 4000 }],
        'cumulative-layout-shift': ['warn', { maxNumericValue: 0.15 }],
        interactive: ['warn', { maxNumericValue: 5000 }],
      },
    },
    upload: {
      target: 'temporary-public-storage',
    },
  },
};
