import type { BooksState, DocKind, DocumentRecord, Party } from "./types";
import { daysBetween, grandTotal, todayIso, vatAmount } from "./money";
import { PLANS } from "./plans";

export function isLive(doc: DocumentRecord): boolean {
  return doc.status !== "void";
}

export function docTotal(doc: DocumentRecord): number {
  if (doc.kind === "letter") return 0;
  return grandTotal(doc.items, doc.vatRate);
}

export function partyById(state: BooksState, id: string): Party | undefined {
  return state.parties.find((p) => p.id === id);
}

export function nextSequence(state: BooksState, kind: DocKind): number {
  return (
    state.documents.filter((d) => d.kind === kind).reduce((m, d) => Math.max(m, d.sequence), 0) + 1
  );
}

export function formatNumber(prefix: string, kind: DocKind, year: string, sequence: number) {
  const code =
    kind === "quotation"
      ? "QTN"
      : kind === "invoice"
        ? "INV"
        : kind === "receipt"
          ? "RCT"
          : kind === "expense"
            ? "EXP"
            : "LTR";
  return `${prefix}-${code}-${year}-${String(sequence).padStart(4, "0")}`;
}

export function paidOnInvoice(state: BooksState, invoiceId: string): number {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "receipt" && d.relatedDocumentId === invoiceId)
    .reduce((sum, d) => sum + (d.allocatedAmount ?? docTotal(d)), 0);
}

export function outstandingInvoice(state: BooksState, doc: DocumentRecord): number {
  if (!isLive(doc) || doc.kind !== "invoice") return 0;
  return Math.max(0, docTotal(doc) - paidOnInvoice(state, doc.id));
}

export function invoiceLabel(state: BooksState, doc: DocumentRecord) {
  if (doc.status === "void") return "void";
  if (doc.kind === "invoice") {
    const due = outstandingInvoice(state, doc);
    if (due <= 0) return "paid";
    if (due < docTotal(doc)) return "part-paid";
    if (daysBetween(doc.dueDate ?? doc.date, todayIso()) > 0) return "overdue";
    return "open";
  }
  return "issued";
}

export function incomeTotal(state: BooksState) {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "invoice")
    .reduce((s, d) => s + docTotal(d), 0);
}

export function collectedTotal(state: BooksState) {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "receipt")
    .reduce((s, d) => s + docTotal(d), 0);
}

export function expenseTotal(state: BooksState) {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "expense")
    .reduce((s, d) => s + docTotal(d), 0);
}

export function profit(state: BooksState) {
  return incomeTotal(state) - expenseTotal(state);
}

export function vatOutput(state: BooksState) {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "invoice")
    .reduce((s, d) => s + vatAmount(d.items, d.vatRate), 0);
}

export function vatInput(state: BooksState) {
  return state.documents
    .filter((d) => isLive(d) && d.kind === "expense")
    .reduce((s, d) => s + vatAmount(d.items, d.vatRate), 0);
}

export type Aging = {
  party: Party;
  current: number;
  days30: number;
  days60: number;
  days90: number;
  total: number;
};

function bucket(amount: number, date: string, due: Aging) {
  const age = daysBetween(date, todayIso());
  if (age <= 30) due.current += amount;
  else if (age <= 60) due.days30 += amount;
  else if (age <= 90) due.days60 += amount;
  else due.days90 += amount;
  due.total += amount;
}

export function debtors(state: BooksState): Aging[] {
  const map = new Map<string, Aging>();
  for (const doc of state.documents) {
    if (doc.kind !== "invoice" || !isLive(doc)) continue;
    const dueAmt = outstandingInvoice(state, doc);
    if (dueAmt <= 0) continue;
    const party = partyById(state, doc.partyId);
    if (!party) continue;
    const row =
      map.get(party.id) ??
      { party, current: 0, days30: 0, days60: 0, days90: 0, total: 0 };
    bucket(dueAmt, doc.dueDate ?? doc.date, row);
    map.set(party.id, row);
  }
  return [...map.values()].sort((a, b) => b.total - a.total);
}

export function creditors(state: BooksState): Aging[] {
  const map = new Map<string, Aging>();
  for (const doc of state.documents) {
    if (doc.kind !== "expense" || !isLive(doc)) continue;
    const party = partyById(state, doc.partyId);
    if (!party || party.kind === "customer") continue;
    const row =
      map.get(party.id) ??
      { party, current: 0, days30: 0, days60: 0, days90: 0, total: 0 };
    bucket(docTotal(doc), doc.date, row);
    map.set(party.id, row);
  }
  return [...map.values()].sort((a, b) => b.total - a.total);
}

export function expensesByCategory(state: BooksState) {
  const map = new Map<string, number>();
  for (const doc of state.documents) {
    if (doc.kind !== "expense" || !isLive(doc)) continue;
    const key = doc.expenseCategory ?? "Other";
    map.set(key, (map.get(key) ?? 0) + docTotal(doc));
  }
  return [...map.entries()]
    .map(([category, amount]) => ({ category, amount }))
    .sort((a, b) => b.amount - a.amount);
}

export function cashBook(state: BooksState) {
  const rows = state.documents
    .filter((d) => isLive(d) && (d.kind === "receipt" || d.kind === "expense"))
    .sort((a, b) => a.date.localeCompare(b.date) || a.sequence - b.sequence)
    .map((d) => ({
      doc: d,
      in: d.kind === "receipt" ? docTotal(d) : 0,
      out: d.kind === "expense" ? docTotal(d) : 0,
    }));
  let balance = 0;
  return rows.map((row) => {
    balance += row.in - row.out;
    return { ...row, balance };
  });
}

export function planAllowsEfris(state: BooksState) {
  return PLANS[state.branding.plan].efris;
}
