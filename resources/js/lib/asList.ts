/**
 * Coerce unknown Inertia/JSON list props into a real array.
 * Optional chaining (`?.find`) does not protect against truthy non-arrays.
 */
export function asList<T>(value: unknown): T[] {
  if (Array.isArray(value)) {
    return value;
  }

  if (value && typeof value === 'object' && Array.isArray((value as { data?: unknown }).data)) {
    return (value as { data: T[] }).data;
  }

  return [];
}
