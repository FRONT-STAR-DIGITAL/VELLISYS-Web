import type { LineItem } from "./types";

const ONES = [
  "",
  "One",
  "Two",
  "Three",
  "Four",
  "Five",
  "Six",
  "Seven",
  "Eight",
  "Nine",
  "Ten",
  "Eleven",
  "Twelve",
  "Thirteen",
  "Fourteen",
  "Fifteen",
  "Sixteen",
  "Seventeen",
  "Eighteen",
  "Nineteen",
];

const TENS = [
  "",
  "",
  "Twenty",
  "Thirty",
  "Forty",
  "Fifty",
  "Sixty",
  "Seventy",
  "Eighty",
  "Ninety",
];

function underThousand(n: number): string {
  if (n === 0) return "";
  if (n < 20) return ONES[n];
  if (n < 100) {
    return TENS[Math.floor(n / 10)] + (n % 10 ? " " + ONES[n % 10] : "");
  }
  const rest = n % 100;
  return (
    ONES[Math.floor(n / 100)] +
    " Hundred" +
    (rest ? " and " + underThousand(rest) : "")
  );
}

export function amountInWords(amount: number): string {
  const n = Math.round(Math.abs(amount));
  if (n === 0) return "Uganda Shillings Zero Only";

  const billions = Math.floor(n / 1_000_000_000);
  const millions = Math.floor((n % 1_000_000_000) / 1_000_000);
  const thousands = Math.floor((n % 1_000_000) / 1_000);
  const rest = n % 1_000;

  const parts: string[] = [];
  if (billions) parts.push(underThousand(billions) + " Billion");
  if (millions) parts.push(underThousand(millions) + " Million");
  if (thousands) parts.push(underThousand(thousands) + " Thousand");
  if (rest) parts.push(underThousand(rest));

  return "Uganda Shillings " + parts.join(" ") + " Only";
}

export function ugx(amount: number): string {
  const rounded = Math.round(amount);
  const formatted = new Intl.NumberFormat("en-UG", {
    maximumFractionDigits: 0,
  }).format(rounded);
  return `UGX ${formatted}`;
}

export function ugxCompact(amount: number): string {
  const abs = Math.abs(amount);
  if (abs >= 1_000_000) {
    const m = amount / 1_000_000;
    return `UGX ${m.toFixed(m >= 10 ? 0 : 1)}m`;
  }
  return ugx(amount);
}

export function lineAmount(item: LineItem): number {
  return Math.round(item.qty * item.rate);
}

export function subtotal(items: LineItem[]): number {
  return items.reduce((sum, item) => sum + lineAmount(item), 0);
}

export function vatAmount(items: LineItem[], vatRate: number): number {
  return Math.round(subtotal(items) * vatRate);
}

export function grandTotal(items: LineItem[], vatRate: number): number {
  return subtotal(items) + vatAmount(items, vatRate);
}

export function formatDate(iso: string): string {
  const [year, month, day] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "short",
    year: "numeric",
    timeZone: "UTC",
  }).format(date);
}

export function todayIso(): string {
  return "2026-09-09";
}

export function daysBetween(fromIso: string, toIso: string): number {
  const from = Date.parse(`${fromIso}T00:00:00Z`);
  const to = Date.parse(`${toIso}T00:00:00Z`);
  return Math.floor((to - from) / 86_400_000);
}
