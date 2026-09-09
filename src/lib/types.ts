export type BookType =
  | "quotation"
  | "invoice"
  | "receipt"
  | "bill"
  | "voucher";

export type CopyKind = "original" | "duplicate" | "counterfoil";

export type PaymentMethod =
  | "cash"
  | "cheque"
  | "mobile-money"
  | "bank-transfer"
  | "eft";

export type PartyKind = "customer" | "supplier" | "both";

export interface LineItem {
  description: string;
  qty: number;
  unit: string;
  rate: number;
}

export interface Allocation {
  documentId: string;
  amount: number;
}

export interface Party {
  id: string;
  name: string;
  kind: PartyKind;
  tin?: string;
  phone?: string;
  address?: string;
}

export interface Company {
  name: string;
  tradingAs?: string;
  tin: string;
  vatNo: string;
  address: string;
  city: string;
  phone: string;
  email: string;
  bankName: string;
  bankBranch: string;
  accountName: string;
  accountNumber: string;
  booksYear: number;
}

export interface DocumentRecord {
  id: string;
  book: BookType;
  sequence: number;
  number: string;
  date: string;
  partyId: string;
  items: LineItem[];
  vatRate: number;
  notes?: string;
  status: "issued" | "void";
  voidReason?: string;
  relatedDocumentId?: string;
  paymentMethod?: PaymentMethod;
  paymentRef?: string;
  allocations?: Allocation[];
}

export interface BooksState {
  company: Company;
  parties: Party[];
  documents: DocumentRecord[];
}

export interface AgingBucket {
  current: number;
  days30: number;
  days60: number;
  days90: number;
  total: number;
}

export interface LedgerRow {
  party: Party;
  invoices: number;
  paid: number;
  outstanding: number;
  aging: AgingBucket;
  lastDate: string | null;
}
