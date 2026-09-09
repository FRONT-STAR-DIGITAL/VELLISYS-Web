"use client";

import {
  createContext,
  useCallback,
  useContext,
  useMemo,
  useSyncExternalStore,
  type ReactNode,
} from "react";
import { makeEfris } from "./efris";
import { formatNumber, nextSequence } from "./reports";
import { seedState } from "./seed";
import type {
  BooksState,
  Branding,
  DocKind,
  DocumentRecord,
  LineItem,
  Party,
  PaymentMethod,
} from "./types";

const STORAGE_KEY = "folio-sme-v1";

type IssueInput = {
  kind: DocKind;
  date: string;
  dueDate?: string;
  partyId: string;
  newParty?: Omit<Party, "id">;
  items: LineItem[];
  vatRate: number;
  notes?: string;
  subject?: string;
  body?: string;
  relatedDocumentId?: string;
  paymentMethod?: PaymentMethod;
  paymentRef?: string;
  allocatedAmount?: number;
  expenseCategory?: string;
};

const SERVER = seedState();
let memory: BooksState | null = null;
const listeners = new Set<() => void>();

function cloneSeed() {
  return structuredClone(seedState());
}

function readStorage(): BooksState {
  try {
    const raw = window.localStorage.getItem(STORAGE_KEY);
    if (!raw) return cloneSeed();
    const parsed = JSON.parse(raw) as BooksState;
    if (!parsed?.branding || !Array.isArray(parsed.documents)) return cloneSeed();
    return parsed;
  } catch {
    return cloneSeed();
  }
}

function emit() {
  listeners.forEach((l) => l());
}

function getSnapshot() {
  if (memory === null) memory = typeof window === "undefined" ? cloneSeed() : readStorage();
  return memory;
}

function write(next: BooksState) {
  memory = next;
  window.localStorage.setItem(STORAGE_KEY, JSON.stringify(next));
  emit();
}

function subscribe(listener: () => void) {
  listeners.add(listener);
  return () => listeners.delete(listener);
}

const Ctx = createContext<{
  state: BooksState;
  issue: (input: IssueInput) => DocumentRecord;
  voidDoc: (id: string, reason: string) => void;
  saveBranding: (branding: Branding) => void;
  resetDemo: () => void;
} | null>(null);

export function BooksProvider({ children }: { children: ReactNode }) {
  const state = useSyncExternalStore(subscribe, getSnapshot, () => SERVER);

  const issue = useCallback((input: IssueInput) => {
    const next = structuredClone(getSnapshot());
    let partyId = input.partyId;
    if (input.newParty) {
      partyId = `pty-${crypto.randomUUID().slice(0, 8)}`;
      next.parties.push({ id: partyId, ...input.newParty });
    }
    const sequence = nextSequence(next, input.kind);
    const year = input.date.slice(0, 4);
    const record: DocumentRecord = {
      id: `${input.kind}-${crypto.randomUUID().slice(0, 8)}`,
      kind: input.kind,
      sequence,
      number: formatNumber(next.branding.prefix, input.kind, year, sequence),
      date: input.date,
      dueDate: input.dueDate,
      partyId,
      items: input.items,
      vatRate: input.vatRate,
      notes: input.notes,
      subject: input.subject,
      body: input.body,
      status: "issued",
      relatedDocumentId: input.relatedDocumentId,
      paymentMethod: input.paymentMethod,
      paymentRef: input.paymentRef,
      allocatedAmount: input.allocatedAmount,
      expenseCategory: input.expenseCategory,
    };
    if (
      next.branding.plan !== "starter" &&
      (record.kind === "invoice" || record.kind === "receipt")
    ) {
      record.efris = makeEfris(next.branding, record);
    }
    next.documents.push(record);
    write(next);
    return record;
  }, []);

  const voidDoc = useCallback((id: string, reason: string) => {
    const next = structuredClone(getSnapshot());
    const doc = next.documents.find((d) => d.id === id);
    if (!doc || doc.status === "void") return;
    doc.status = "void";
    doc.voidReason = reason;
    write(next);
  }, []);

  const saveBranding = useCallback((branding: Branding) => {
    const next = structuredClone(getSnapshot());
    next.branding = branding;
    write(next);
  }, []);

  const resetDemo = useCallback(() => write(cloneSeed()), []);

  const value = useMemo(
    () => ({ state, issue, voidDoc, saveBranding, resetDemo }),
    [state, issue, voidDoc, saveBranding, resetDemo],
  );

  return <Ctx.Provider value={value}>{children}</Ctx.Provider>;
}

export function useBooks() {
  const ctx = useContext(Ctx);
  if (!ctx) throw new Error("useBooks must be used within BooksProvider");
  return ctx;
}
