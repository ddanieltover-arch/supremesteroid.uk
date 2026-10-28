import { useEffect, useMemo, useState } from 'react';

type GalleryImage = {
  id: string;
  url: string;
  alt_text?: string | null;
  caption?: string | null;
  thumbnail_url?: string | null;
  is_primary: boolean;
  variant_id?: string | null;
};

export function ProductGallery({ images, variantId }: { images: GalleryImage[]; variantId?: string }) {
  const visible = useMemo(() => {
    if (variantId) {
      const matched = images.filter((image) => image.variant_id === variantId && Boolean(image.url));
      if (matched.length > 0) {
        return matched;
      }
    }

    // Fallback: product-level gallery only (exclude unrelated variant media).
    const productLevel = images.filter((image) => ! image.variant_id && Boolean(image.url));
    if (productLevel.length > 0) {
      return productLevel;
    }

    // Final fallback when no product-level images exist and no variant was selected.
    if (! variantId) {
      return images.filter((image) => Boolean(image.url));
    }

    return [];
  }, [images, variantId]);

  const [active, setActive] = useState(0);
  const [failed, setFailed] = useState(false);

  useEffect(() => {
    setActive(0);
    setFailed(false);
  }, [visible]);

  const current = visible[active];

  const move = (delta: number) => {
    if (visible.length === 0) {
      return;
    }
    setFailed(false);
    setActive((index) => (index + delta + visible.length) % visible.length);
  };

  return (
    <div>
      <div
        className="relative aspect-square overflow-hidden rounded-2xl border border-slate-200 bg-white"
        tabIndex={0}
        role="region"
        aria-label="Product images"
        onKeyDown={(event) => {
          if (event.key === 'ArrowRight') {
            event.preventDefault();
            move(1);
          }
          if (event.key === 'ArrowLeft') {
            event.preventDefault();
            move(-1);
          }
        }}
      >
        {current && !failed ? (
          <img
            src={current.url}
            alt={current.alt_text || current.caption || 'Product image'}
            className="h-full w-full object-contain max-md:hidden"
            onError={() => setFailed(true)}
          />
        ) : (
          <div className="hidden h-full items-center justify-center text-sm text-slate-500 md:flex">Image unavailable</div>
        )}

        <div className="flex h-full snap-x snap-mandatory overflow-x-auto md:hidden">
          {visible.length > 0 ? (
            visible.map((image, index) => (
              <img
                key={image.id}
                src={image.url}
                alt={image.alt_text || image.caption || 'Product image'}
                className="h-full w-full shrink-0 snap-center object-contain"
                loading={index === 0 ? 'eager' : 'lazy'}
              />
            ))
          ) : (
            <div className="flex h-full w-full items-center justify-center text-sm text-slate-500">No image</div>
          )}
        </div>
      </div>

      {visible.length > 1 ? (
        <div className="mt-3 hidden gap-2 md:flex" role="listbox" aria-label="Image thumbnails">
          {visible.map((image, index) => (
            <button
              key={image.id}
              type="button"
              role="option"
              aria-selected={index === active}
              onClick={() => {
                setFailed(false);
                setActive(index);
              }}
              className={`h-16 w-16 overflow-hidden rounded-lg border ${index === active ? 'border-blue-500' : 'border-slate-200'}`}
            >
              <img src={image.thumbnail_url || image.url} alt="" className="h-full w-full object-contain" loading="lazy" />
            </button>
          ))}
        </div>
      ) : null}
    </div>
  );
}
