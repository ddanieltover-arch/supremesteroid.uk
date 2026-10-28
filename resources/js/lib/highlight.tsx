import type { ReactNode } from 'react';

export function highlightMatch(text: string, query: string): ReactNode {
  const term = query.trim();
  if (term.length < 2) {
    return text;
  }
  const index = text.toLowerCase().indexOf(term.toLowerCase());
  if (index === -1) {
    return text;
  }
  return (
    <>
      {text.slice(0, index)}
      <mark className="rounded-sm bg-blue-600/20 text-blue-700">{text.slice(index, index + term.length)}</mark>
      {text.slice(index + term.length)}
    </>
  );
}
