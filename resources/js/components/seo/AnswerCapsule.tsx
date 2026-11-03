export function AnswerCapsule({
  answer,
  label = 'Quick answer',
}: {
  answer?: string | null;
  label?: string;
}) {
  const text = (answer || '').trim();
  if (!text) {
    return null;
  }

  return (
    <section
      id="answer"
      aria-label={label}
      className="mx-auto max-w-3xl rounded-2xl border border-slate-200 bg-white px-5 py-4 text-left shadow-sm sm:px-6"
    >
      <p className="text-sm leading-relaxed text-slate-700 sm:text-base">
        <strong className="font-semibold text-slate-900">{label}:</strong> {text}
      </p>
    </section>
  );
}
