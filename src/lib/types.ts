export type PlanId = "starter" | "sme" | "office";

export type DocKind =
  | "quotation"
  | "invoice"
  | "receipt"
  | "expense"
  | "letter";

export type PaymentMethod =
  | "cash"
  | "cheque"
  | "mobile-money"
  | "bank-transfer"
  | "pesapal"
  | "other";

export interface LineItem {
  description: string;
  qty: number;
  unit: string;
  rate: number;
  taxed: boolean;
}

export interface Party {
  id: string;
  name: string;
  kind: "customer" | "supplier" | "both";
  tin?: string;
  phone?: string;
  email?: string;
  address?: string;
}

export interface Branding {
  name: string;
  tagline: string;
  tin: string;
  vatNo: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  website: string;
  bankName: string;
  accountName: string;
  accountNumber: string;
  brandColor: string;
  logoDataUrl: string;
  prefix: string;
  paymentNote: string;
  invoiceComments: string;
  plan: PlanId;
}

export interface EfrisMark {
  fdn: string;
  verification: string;
  issuedAt: string;
  payload: string;
}

export interface DocumentRecord {
  id: string;
  kind: DocKind;
  sequence: number;
  number: string;
  date: string;
  dueDate?: string;
  partyId: string;
  items: LineItem[];
  vatRate: number;
  notes?: string;
  subject?: string;
  body?: string;
  status: "issued" | "void";
  voidReason?: string;
  relatedDocumentId?: string;
  paymentMethod?: PaymentMethod;
  paymentRef?: string;
  allocatedAmount?: number;
  expenseCategory?: string;
  efris?: EfrisMark;
}

export interface BooksState {
  branding: Branding;
  parties: Party[];
  documents: DocumentRecord[];
}
