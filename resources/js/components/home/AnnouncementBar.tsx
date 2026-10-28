import { router, usePage } from '@inertiajs/react';
import { X } from 'lucide-react';

export function AnnouncementBar() {
  const page = usePage<{
    chrome?: { announcement?: { enabled?: boolean; message?: string; signature?: string; dismissible?: boolean } | null };
  }>();
  const announcement = page.props.chrome?.announcement;
  if (!announcement?.enabled || !announcement.message) {
    return null;
  }

  return (
    <div className="overflow-x-clip bg-blue-600 px-3 py-2.5 text-white">
      <div className="mx-auto flex max-w-7xl items-center justify-center gap-2 sm:gap-3">
        <p className="min-w-0 flex-1 text-pretty text-center text-xs font-semibold leading-5">{announcement.message}</p>
        {announcement.dismissible && announcement.signature ? (
          <button
            type="button"
            className="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-white hover:bg-white/15 focus-visible:ring-2 focus-visible:ring-white"
            aria-label="Dismiss announcement"
            onClick={() => router.post('/announcement/dismiss', { signature: announcement.signature }, { preserveScroll: true, preserveState: true })}
          >
            <X className="h-4 w-4" />
          </button>
        ) : null}
      </div>
    </div>
  );
}
