import type { LineItem } from "./types";

export function ugx(amount: number): string {
  const rounded = Math.round(amount);
  return `UGX ${new Intl.NumberFormat("en-UG", {
    maximumFractionDigits: 0,
  }).format(rounded)}`;
}

export function formatDate(iso: string): string {
  const [year, month, day] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return new Intl.DateTimeFormat("en-GB", {
    day: "2-digit",
    month: "2-digit",
    year: "numeric",
    timeZone: "UTC",
  }).format(date);
}

export function formatDateLong(iso: string): string {
  const [year, month, day] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day));
  return new Intl.DateTimeFormat("en-GB", {
    day: "numeric",
    month: "long",
    year: "numeric",
    timeZone: "UTC",
  }).format(date);
}

export function todayIso(): string {
  return "2026-09-09";
}

export function addDays(iso: string, days: number): string {
  const [year, month, day] = iso.split("-").map(Number);
  const date = new Date(Date.UTC(year, month - 1, day + days));
  return date.toISOString().slice(0, 10);
}

export function daysBetween(fromIso: string, toIso: string): number {
  const from = Date.parse(`${fromIso}T00:00:00Z`);
  const to = Date.parse(`${toIso}T00:00:00Z`);
  return Math.floor((to - from) / 86_400_000);
}

export function lineAmount(item: LineItem): number {
  return Math.round(item.qty * item.rate);
}

export function subtotal(items: LineItem[]): number {
  return items.reduce((sum, item) => sum + lineAmount(item), 0);
}

export function vatAmount(items: LineItem[], vatRate: number): number {
  if (vatRate <= 0) return 0;
  const base = items
    .filter((item) => item.taxed)
    .reduce((sum, item) => sum + lineAmount(item), 0);
  return Math.round(base * vatRate);
}

export function grandTotal(items: LineItem[], vatRate: number): number {
  return subtotal(items) + vatAmount(items, vatRate);
}

export function hexTint(hex: string, mixWhite = 0.9): string {
  const clean = hex.replace("#", "");
  const n = parseInt(clean.length === 3 ? clean.replace(/./g, (c) => c + c) : clean, 16);
  const r = (n >> 16) & 255;
  const g = (n >> 8) & 255;
  const b = n & 255;
  const mix = (c: number) => Math.round(c * (1 - mixWhite) + 255 * mixWhite);
  return `rgb(${mix(r)}, ${mix(g)}, ${mix(b)})`;
}

export const EXPENSE_CATEGORIES = [
  "Farm inputs",
  "Transport",
  "Fuel",
  "Rent",
  "Utilities",
  "Salaries",
  "Professional fees",
  "Repairs",
  "Marketing",
  "Other",
] as const;
