import type {
  AgingBucket,
  BookType,
  BooksState,
  DocumentRecord,
  LedgerRow,
  Party,
} from "./types";
import { BOOK_META } from "./books";
import { daysBetween, grandTotal, todayIso } from "./money";

export function isLive(doc: DocumentRecord): boolean {
  return doc.status !== "void";
}

export function documentTotal(doc: DocumentRecord): number {
  return grandTotal(doc.items, doc.vatRate);
}

export function allocatedTo(
  state: BooksState,
  documentId: string,
  fromBook: BookType,
): number {
  return state.documents
    .filter(
      (doc) =>
        isLive(doc) &&
        doc.book === fromBook &&
        (doc.allocations?.some((a) => a.documentId === documentId) ?? false),
    )
    .reduce((sum, doc) => {
      const bit = doc.allocations
        ?.filter((a) => a.documentId === documentId)
        .reduce((s, a) => s + a.amount, 0);
      return sum + (bit ?? 0);
    }, 0);
}

export function outstandingOn(
  state: BooksState,
  doc: DocumentRecord,
): number {
  if (!isLive(doc)) return 0;
  if (doc.book === "invoice") {
    return Math.max(0, documentTotal(doc) - allocatedTo(state, doc.id, "receipt"));
  }
  if (doc.book === "bill") {
    return Math.max(0, documentTotal(doc) - allocatedTo(state, doc.id, "voucher"));
  }
  return 0;
}

export function invoiceStatusLabel(
  state: BooksState,
  doc: DocumentRecord,
): "paid" | "part-paid" | "overdue" | "open" | "void" | "issued" {
  if (doc.status === "void") return "void";
  if (doc.book === "invoice" || doc.book === "bill") {
    const due = outstandingOn(state, doc);
    if (due <= 0) return "paid";
    const paid = documentTotal(doc) - due;
    if (paid > 0) return "part-paid";
    if (daysBetween(doc.date, todayIso()) > 30) return "overdue";
    return "open";
  }
  if (doc.book === "quotation") {
    const converted = state.documents.some(
      (d) => isLive(d) && d.book === "invoice" && d.relatedDocumentId === doc.id,
    );
    return converted ? "issued" : "open";
  }
  return "issued";
}

function emptyAging(): AgingBucket {
  return { current: 0, days30: 0, days60: 0, days90: 0, total: 0 };
}

function addAging(bucket: AgingBucket, amount: number, date: string) {
  const age = daysBetween(date, todayIso());
  if (age <= 30) bucket.current += amount;
  else if (age <= 60) bucket.days30 += amount;
  else if (age <= 90) bucket.days60 += amount;
  else bucket.days90 += amount;
  bucket.total += amount;
}

export function buildLedger(
  state: BooksState,
  kind: "debtors" | "creditors",
): LedgerRow[] {
  const sourceBook: BookType = kind === "debtors" ? "invoice" : "bill";
  const payBook: BookType = kind === "debtors" ? "receipt" : "voucher";
  const partyKind = kind === "debtors" ? "customer" : "supplier";

  const rows: LedgerRow[] = [];

  for (const party of state.parties) {
    if (party.kind !== partyKind && party.kind !== "both") continue;
    const docs = state.documents.filter(
      (d) => isLive(d) && d.book === sourceBook && d.partyId === party.id,
    );
    const pays = state.documents.filter(
      (d) => isLive(d) && d.book === payBook && d.partyId === party.id,
    );
    if (docs.length === 0 && pays.length === 0) continue;

    const invoiced = docs.reduce((s, d) => s + documentTotal(d), 0);
    const paid = pays.reduce((s, d) => s + documentTotal(d), 0);
    const aging = emptyAging();
    let lastDate: string | null = null;

    for (const d of docs) {
      const due = outstandingOn(state, d);
      if (due > 0) addAging(aging, due, d.date);
      if (!lastDate || d.date > lastDate) lastDate = d.date;
    }
    for (const d of pays) {
      if (!lastDate || d.date > lastDate) lastDate = d.date;
    }

    rows.push({
      party,
      invoices: invoiced,
      paid,
      outstanding: aging.total,
      aging,
      lastDate,
    });
  }

  return rows.sort((a, b) => b.outstanding - a.outstanding);
}

export function partyById(state: BooksState, id: string): Party | undefined {
  return state.parties.find((p) => p.id === id);
}

export function nextSequence(state: BooksState, book: BookType): number {
  const max = state.documents
    .filter((d) => d.book === book)
    .reduce((m, d) => Math.max(m, d.sequence), 0);
  return max + 1;
}

export function pagesUsed(state: BooksState, book: BookType): number {
  return state.documents.filter((d) => d.book === book).length;
}

export function bookTotals(state: BooksState, book: BookType) {
  const docs = state.documents.filter((d) => d.book === book && isLive(d));
  const issued = docs.reduce((s, d) => s + documentTotal(d), 0);
  return { count: docs.length, issued };
}

export function openByParty(
  state: BooksState,
  partyId: string,
  book: BookType,
): DocumentRecord[] {
  return state.documents.filter(
    (d) =>
      isLive(d) &&
      d.book === book &&
      d.partyId === partyId &&
      outstandingOn(state, d) > 0,
  );
}

export function documentsForParty(
  state: BooksState,
  partyId: string,
  books: BookType[],
): DocumentRecord[] {
  return state.documents
    .filter((d) => d.partyId === partyId && books.includes(d.book))
    .sort((a, b) => (a.date < b.date ? 1 : -1));
}

export { BOOK_META };
