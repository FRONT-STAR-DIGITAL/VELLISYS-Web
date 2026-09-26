/** Same style as Vellisys PHP money(): "UGX 1,121,000" */
export function formatMoney(amount: number, currency = 'UGX'): string {
  const cur = (currency || 'UGX').toUpperCase();
  const n = Number.isFinite(amount) ? amount : 0;
  const dec = Math.abs(n % 1) < 0.0000001 ? 0 : 2;
  return `${cur} ${n.toLocaleString('en-US', {
    minimumFractionDigits: dec,
    maximumFractionDigits: dec,
  })}`;
}

export function formatQty(qty: number): string {
  const n = Number.isFinite(qty) ? qty : 0;
  if (Math.abs(n % 1) < 0.0000001) return String(Math.round(n));
  return n.toLocaleString('en-US', { maximumFractionDigits: 4 });
}

export function formatDateIso(iso?: string): string {
  if (!iso) return '—';
  const d = new Date(iso + (iso.length === 10 ? 'T12:00:00' : ''));
  if (Number.isNaN(d.getTime())) return iso;
  return d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
}
