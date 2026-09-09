"use client";

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { formatBookNumber } from "./books";
import { allocatedTo, nextSequence } from "./ledgers";
import { grandTotal } from "./money";
import { seedState } from "./seed";
import type {
  BookType,
  BooksState,
  DocumentRecord,
  LineItem,
  Party,
  PaymentMethod,
} from "./types";

const STORAGE_KEY = "counterfoil-books-v1";

type IssueInput = {
  book: BookType;
  date: string;
  partyId: string;
  newParty?: Omit<Party, "id">;
  items: LineItem[];
  vatRate: number;
  notes?: string;
  relatedDocumentId?: string;
  paymentMethod?: PaymentMethod;
  paymentRef?: string;
  allocations?: { documentId: string; amount: number }[];
};

type BooksContextValue = {
  state: BooksState;
  hydrated: boolean;
  issue: (input: IssueInput) => DocumentRecord;
  voidDocument: (id: string, reason: string) => void;
  resetDemo: () => void;
  convertQuotation: (quotationId: string, date: string) => DocumentRecord | null;
  receiveAgainst: (
    invoiceId: string,
    amount: number,
    date: string,
    method: PaymentMethod,
    ref: string,
  ) => DocumentRecord | null;
  payAgainst: (
    billId: string,
    amount: number,
    date: string,
    method: PaymentMethod,
    ref: string,
  ) => DocumentRecord | null;
};

const BooksContext = createContext<BooksContextValue | null>(null);

function cloneSeed(): BooksState {
  return structuredClone(seedState());
}

const SERVER_SNAPSHOT = cloneSeed();

function readStorage(): BooksState {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return cloneSeed();
    const parsed = JSON.parse(raw) as BooksState;
    if (!parsed?.company || !Array.isArray(parsed.documents)) return cloneSeed();
    return parsed;
  } catch {
    return cloneSeed();
  }
}

function persist(state: BooksState) {
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
}

let memory: BooksState | null = null;
const listeners = new Set<() => void>();

function emit() {
  listeners.forEach((listener) => listener());
}

function getClientSnapshot(): BooksState {
  if (memory === null) {
    memory = typeof window === "undefined" ? cloneSeed() : readStorage();
  }
  return memory;
}

function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

function write(next: BooksState) {
  memory = next;
  persist(next);
  emit();
}

export function BooksProvider({ children }: { children: ReactNode }) {
  const state = useSyncExternalStore(
    subscribe,
    getClientSnapshot,
    () => SERVER_SNAPSHOT,
  );

  const issue = useCallback(
    (input: IssueInput) => {
      const next = structuredClone(getClientSnapshot());
      let partyId = input.partyId;

      if (input.newParty) {
        partyId = `pty-${crypto.randomUUID().slice(0, 8)}`;
        next.parties.push({ id: partyId, ...input.newParty });
      }

      const sequence = nextSequence(next, input.book);
      const record: DocumentRecord = {
        id: `${input.book}-${crypto.randomUUID().slice(0, 8)}`,
        book: input.book,
        sequence,
        number: formatBookNumber(input.book, next.company.booksYear, sequence),
        date: input.date,
        partyId,
        items: input.items,
        vatRate: input.vatRate,
        notes: input.notes,
        status: "issued",
        relatedDocumentId: input.relatedDocumentId,
        paymentMethod: input.paymentMethod,
        paymentRef: input.paymentRef,
        allocations: input.allocations,
      };
      next.documents.push(record);
      write(next);
      return record;
    },
    [],
  );

  const voidDocument = useCallback((id: string, reason: string) => {
    const next = structuredClone(getClientSnapshot());
    const doc = next.documents.find((d) => d.id === id);
    if (!doc || doc.status === "void") return;
    doc.status = "void";
    doc.voidReason = reason;
    write(next);
  }, []);

  const resetDemo = useCallback(() => {
    write(cloneSeed());
  }, []);

  const convertQuotation = useCallback(
    (quotationId: string, date: string) => {
      const current = getClientSnapshot();
      const quotation = current.documents.find((d) => d.id === quotationId);
      if (!quotation || quotation.book !== "quotation") return null;
      const already = current.documents.some(
        (d) =>
          d.book === "invoice" &&
          d.relatedDocumentId === quotationId &&
          d.status !== "void",
      );
      if (already) return null;
      return issue({
        book: "invoice",
        date,
        partyId: quotation.partyId,
        items: quotation.items,
        vatRate: quotation.vatRate,
        notes: `Converted from ${quotation.number}.`,
        relatedDocumentId: quotation.id,
      });
    },
    [issue],
  );

  const receiveAgainst = useCallback(
    (
      invoiceId: string,
      amount: number,
      date: string,
      method: PaymentMethod,
      ref: string,
    ) => {
      const current = getClientSnapshot();
      const invoice = current.documents.find((d) => d.id === invoiceId);
      if (!invoice) return null;
      const already = allocatedTo(current, invoiceId, "receipt");
      const due = Math.max(0, grandTotal(invoice.items, invoice.vatRate) - already);
      const take = Math.min(amount, due);
      if (take <= 0) return null;
      return issue({
        book: "receipt",
        date,
        partyId: invoice.partyId,
        items: [
          {
            description: `Payment on account — ${invoice.number}`,
            qty: 1,
            unit: "lot",
            rate: take,
          },
        ],
        vatRate: 0,
        notes: `Received with thanks against ${invoice.number}.`,
        relatedDocumentId: invoice.id,
        paymentMethod: method,
        paymentRef: ref,
        allocations: [{ documentId: invoice.id, amount: take }],
      });
    },
    [issue],
  );

  const payAgainst = useCallback(
    (
      billId: string,
      amount: number,
      date: string,
      method: PaymentMethod,
      ref: string,
    ) => {
      const current = getClientSnapshot();
      const bill = current.documents.find((d) => d.id === billId);
      if (!bill) return null;
      const already = allocatedTo(current, billId, "voucher");
      const due = Math.max(0, grandTotal(bill.items, bill.vatRate) - already);
      const take = Math.min(amount, due);
      if (take <= 0) return null;
      return issue({
        book: "voucher",
        date,
        partyId: bill.partyId,
        items: [
          {
            description: `Payment of ${bill.number}`,
            qty: 1,
            unit: "lot",
            rate: take,
          },
        ],
        vatRate: 0,
        notes: `Being settlement against ${bill.number}.`,
        relatedDocumentId: bill.id,
        paymentMethod: method,
        paymentRef: ref,
        allocations: [{ documentId: bill.id, amount: take }],
      });
    },
    [issue],
  );

  const value = useMemo(
    () => ({
      state,
      hydrated: true,
      issue,
      voidDocument,
      resetDemo,
      convertQuotation,
      receiveAgainst,
      payAgainst,
    }),
    [
      state,
      issue,
      voidDocument,
      resetDemo,
      convertQuotation,
      receiveAgainst,
      payAgainst,
    ],
  );

  return <BooksContext.Provider value={value}>{children}</BooksContext.Provider>;
}

export function useBooks() {
  const ctx = useContext(BooksContext);
  if (!ctx) throw new Error("useBooks must be used within BooksProvider");
  return ctx;
}
