import type { PlanId } from "./types";

export const PLANS: Record<
  PlanId,
  {
    id: PlanId;
    name: string;
    price: number;
    priceLabel: string;
    blurb: string;
    efris: boolean;
    users: number;
    points: string[];
  }
> = {
  starter: {
    id: "starter",
    name: "Starter",
    price: 150_000,
    priceLabel: "UGX 150,000",
    blurb: "Logo, colour, and PDFs. For a one-person desk that emails quotations and invoices.",
    efris: false,
    users: 1,
    points: [
      "Your logo and colour on every page",
      "Quotations, invoices, receipts, letters",
      "Expenses and a simple P&L",
      "Download PDF or email the client",
    ],
  },
  sme: {
    id: "sme",
    name: "SME",
    price: 250_000,
    priceLabel: "UGX 250,000",
    blurb: "The Ofagros-style invoice plus VAT and EFRIS marks. This is the plan to sell.",
    efris: true,
    users: 1,
    points: [
      "Everything in Starter",
      "VAT 18% on the document",
      "EFRIS FDN, verification and QR (demo fiscalisation)",
      "Debtors, creditors, VAT worksheet",
    ],
  },
  office: {
    id: "office",
    name: "Office",
    price: 350_000,
    priceLabel: "UGX 350,000",
    blurb: "Same books, up to three people — owner, cashier, secretary.",
    efris: true,
    users: 3,
    points: [
      "Everything in SME",
      "Three people on the same branding",
      "Nothing else piled on",
    ],
  },
};

export const KIND_META = {
  quotation: {
    slug: "quotations",
    title: "Quotations",
    singular: "Quotation",
    verb: "New quotation",
    heading: "QUOTATION",
  },
  invoice: {
    slug: "invoices",
    title: "Invoices",
    singular: "Invoice",
    verb: "New invoice",
    heading: "INVOICE",
  },
  receipt: {
    slug: "receipts",
    title: "Receipts",
    singular: "Receipt",
    verb: "New receipt",
    heading: "RECEIPT",
  },
  expense: {
    slug: "expenses",
    title: "Expenses",
    singular: "Expense",
    verb: "Record expense",
    heading: "EXPENSE",
  },
  letter: {
    slug: "letters",
    title: "Letters",
    singular: "Letter",
    verb: "New letter",
    heading: "LETTER",
  },
} as const;
