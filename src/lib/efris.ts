import type { Branding, DocumentRecord, EfrisMark } from "./types";
import { grandTotal } from "./money";

function hash(input: string): string {
  let h = 2166136261;
  for (let i = 0; i < input.length; i++) {
    h ^= input.charCodeAt(i);
    h = Math.imul(h, 16777619);
  }
  return (h >>> 0).toString(16).toUpperCase().padStart(8, "0");
}

export function makeEfris(
  branding: Branding,
  doc: Pick<DocumentRecord, "number" | "date" | "items" | "vatRate">,
): EfrisMark {
  const total = grandTotal(doc.items, doc.vatRate);
  const stamp = `${branding.tin}|${doc.number}|${doc.date}|${total}`;
  const verification = hash(stamp).slice(0, 8);
  const compactDate = doc.date.replaceAll("-", "");
  const fdn = `256${compactDate}${verification.slice(0, 6)}`;
  const payload = JSON.stringify({
    fdn,
    tin: branding.tin,
    invoice: doc.number,
    date: doc.date,
    amount: total,
    verification,
  });
  return {
    fdn,
    verification,
    issuedAt: `${doc.date}T12:00:00+03:00`,
    payload,
  };
}
