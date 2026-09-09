import type { BookType, CopyKind, PaymentMethod } from "./types";

export const BOOK_META: Record<
  BookType,
  {
    slug: string;
    title: string;
    short: string;
    prefix: string;
    stationeryTitle: string;
    partyRole: "customer" | "supplier";
    colour: string;
    blurb: string;
    empty: string;
    verb: string;
  }
> = {
  quotation: {
    slug: "quotations",
    title: "Quotation Book",
    short: "Quotations",
    prefix: "QTN",
    stationeryTitle: "QUOTATION",
    partyRole: "customer",
    colour: "sky",
    blurb: "Prices you have offered. Convert an accepted quote into an invoice without retyping.",
    empty: "No quotations in this year’s book yet.",
    verb: "Issue quotation",
  },
  invoice: {
    slug: "invoices",
    title: "Invoice Book",
    short: "Invoices",
    prefix: "INV",
    stationeryTitle: "TAX INVOICE",
    partyRole: "customer",
    colour: "forest",
    blurb: "What customers owe you. Each invoice opens a line in the debtors book.",
    empty: "No invoices in this year’s book yet.",
    verb: "Issue invoice",
  },
  receipt: {
    slug: "receipts",
    title: "Official Receipt Book",
    short: "Receipts",
    prefix: "RCT",
    stationeryTitle: "OFFICIAL RECEIPT",
    partyRole: "customer",
    colour: "stamp",
    blurb: "Money in. Allocate to an invoice and the debtor balance falls on its own.",
    empty: "No receipts in this year’s book yet.",
    verb: "Issue receipt",
  },
  bill: {
    slug: "bills",
    title: "Bills Book",
    short: "Bills",
    prefix: "BIL",
    stationeryTitle: "SUPPLIER BILL",
    partyRole: "supplier",
    colour: "amber",
    blurb: "Invoices you have accepted from suppliers. These are your creditors.",
    empty: "No supplier bills in this year’s book yet.",
    verb: "Record bill",
  },
  voucher: {
    slug: "vouchers",
    title: "Payment Voucher Book",
    short: "Vouchers",
    prefix: "PV",
    stationeryTitle: "PAYMENT VOUCHER",
    partyRole: "supplier",
    colour: "ink",
    blurb: "Money out. Allocate to a bill and the creditor balance falls on its own.",
    empty: "No payment vouchers in this year’s book yet.",
    verb: "Issue voucher",
  },
};

export const BOOK_ORDER: BookType[] = [
  "quotation",
  "invoice",
  "receipt",
  "bill",
  "voucher",
];

export const COPY_META: Record<
  CopyKind,
  { label: string; hint: string }
> = {
  original: {
    label: "ORIGINAL",
    hint: "Customer / payee copy — tear off and hand over.",
  },
  duplicate: {
    label: "DUPLICATE",
    hint: "Accounts copy — yellow carbon, stays with the clerk.",
  },
  counterfoil: {
    label: "COUNTERFOIL",
    hint: "The stub that never leaves the book.",
  },
};

export const PAYMENT_METHODS: { value: PaymentMethod; label: string }[] = [
  { value: "cash", label: "Cash" },
  { value: "cheque", label: "Cheque" },
  { value: "mobile-money", label: "Mobile money" },
  { value: "bank-transfer", label: "Bank transfer" },
  { value: "eft", label: "EFT" },
];

export function bookFromSlug(slug: string): BookType | null {
  const entry = (Object.entries(BOOK_META) as [BookType, (typeof BOOK_META)[BookType]][])
    .find(([, meta]) => meta.slug === slug);
  return entry ? entry[0] : null;
}

export function padSequence(n: number): string {
  return String(n).padStart(4, "0");
}

export function formatBookNumber(
  book: BookType,
  year: number,
  sequence: number,
): string {
  return `${BOOK_META[book].prefix}-${year}-${padSequence(sequence)}`;
}
