export function CountBadge({ count, label }: { count: number; label: string }) {
  return (
    <span
      className={`absolute -right-1.5 -top-1.5 flex h-5 min-w-5 items-center justify-center rounded-full bg-amber-500 px-1 text-[11px] font-bold text-slate-950 ${count > 0 ? '' : 'invisible'}`}
      aria-hidden={count === 0}
    >
      <span className="sr-only">{label}: </span>
      {count}
    </span>
  );
}
