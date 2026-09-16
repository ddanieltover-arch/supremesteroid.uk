export function formatMoney(amount: string | number | null | undefined, currency = 'GBP'): string {
  const numeric = typeof amount === 'number' ? amount : Number(amount ?? 0);
  return new Intl.NumberFormat('en-GB', {
    style: 'currency',
    currency,
  }).format(Number.isFinite(numeric) ? numeric : 0);
}
