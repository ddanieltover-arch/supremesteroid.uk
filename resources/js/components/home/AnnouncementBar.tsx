import { usePage } from '@inertiajs/react';

export function AnnouncementBar() {
  const page = usePage<{
    chrome?: { announcement?: { enabled?: boolean; message?: string } | null };
  }>();
  const announcement = page.props.chrome?.announcement;
  if (!announcement?.enabled || !announcement.message) {
    return null;
  }

  return (
    <div className="overflow-x-clip bg-blue-600 px-3 py-2.5 text-white">
      <div className="mx-auto flex max-w-7xl items-center justify-center">
        <p className="min-w-0 text-pretty text-center text-xs font-semibold leading-5">{announcement.message}</p>
      </div>
    </div>
  );
}
