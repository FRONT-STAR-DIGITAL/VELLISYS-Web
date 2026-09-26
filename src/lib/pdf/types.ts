export type FullParty = {
  name: string;
  email?: string;
  phone?: string;
  address?: string;
  tin?: string;
  contact?: string;
};

export type FullLine = {
  description: string;
  itemName?: string;
  itemDescription?: string;
  quantity: number;
  unit?: string;
  unitPrice: number;
  taxRate: number;
  taxable: boolean;
  lineTotal: number;
};

export type FullBrand = {
  name: string;
  tagline?: string;
  address?: string;
  city?: string;
  phone?: string;
  email?: string;
  tin?: string;
  website?: string;
  logoUrl?: string;
  signatureUrl?: string;
  color: string;
  colorAccent?: string;
  colorDeep?: string;
};

export type FullPerson = {
  fullName: string;
  title?: string;
  signaturePath?: string;
};

export type FullDocument = {
  id: number;
  number: string;
  kind: string;
  kindLabel: string;
  heading: string;
  status: string;
  statusLabel?: string;
  canExport: boolean;
  issueDate: string;
  dueDate?: string;
  currency: string;
  subtotal: number;
  taxTotal: number;
  taxRate?: number;
  discount: number;
  total: number;
  amountPaid?: number;
  balanceDue?: number;
  notes?: string;
  terms?: string;
  subject?: string;
  body?: string;
  verifyToken?: string;
  verifyUrl?: string;
  shareUrl?: string;
  client: FullParty;
  lines: FullLine[];
  brand: FullBrand;
  createdBy?: FullPerson | null;
  approvedBy?: FullPerson | null;
  approvedAt?: string | null;
};
