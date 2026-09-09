import { formatNumber } from "./reports";
import type { BooksState, DocumentRecord, LineItem, Party } from "./types";
import { makeEfris } from "./efris";

const taxed = (
  description: string,
  qty: number,
  rate: number,
  unit = "lot",
  isTaxed = true,
): LineItem => ({ description, qty, unit, rate, taxed: isTaxed });

function numbered(
  partial: Omit<DocumentRecord, "number"> & { number?: string },
  prefix: string,
): DocumentRecord {
  const year = partial.date.slice(0, 4);
  return {
    ...partial,
    number: partial.number ?? formatNumber(prefix, partial.kind, year, partial.sequence),
  };
}

const parties: Party[] = [
  {
    id: "pty-demo",
    name: "Demo Visitor",
    kind: "customer",
    phone: "+256700000001",
    email: "demo@ofagros.com",
    address: "Kampala, Uganda",
  },
  {
    id: "pty-nile",
    name: "Nile Coffee Traders Ltd",
    kind: "customer",
    tin: "1000456710",
    phone: "+256 414 220 118",
    email: "accounts@nilecoffee.ug",
    address: "Plot 8, Portal Avenue, Kampala",
  },
  {
    id: "pty-kituza",
    name: "Kituza Estate Growers",
    kind: "customer",
    phone: "+256 772 441 090",
    email: "kituza@growers.ug",
    address: "Mukono District",
  },
  {
    id: "pty-pearl",
    name: "Pearl Hotel Kampala",
    kind: "customer",
    tin: "1000021766",
    phone: "+256 414 251 510",
    email: "purchasing@pearlhotel.ug",
    address: "Kololo, Kampala",
  },
  {
    id: "pty-seedco",
    name: "SeedCo Uganda Ltd",
    kind: "supplier",
    tin: "1000038891",
    phone: "+256 414 566 200",
    address: "Namanve Industrial Park",
  },
  {
    id: "pty-shell",
    name: "Vivo Energy Uganda",
    kind: "supplier",
    tin: "1000023301",
    address: "Kampala",
  },
];

function seedDocuments(prefix: string, branding: BooksState["branding"]): DocumentRecord[] {
  const docs: DocumentRecord[] = [
    numbered(
      {
        id: "qtn-1",
        kind: "quotation",
        sequence: 1,
        date: "2026-07-28",
        dueDate: "2026-08-28",
        partyId: "pty-kituza",
        vatRate: 0.18,
        status: "issued",
        notes: "Prices held 30 days. Delivery to Mukono.",
        items: [
          taxed("Shade-grown robusta seedlings, 6 months", 400, 4_500, "pcs"),
          taxed("Farm visit and planting supervision", 1, 850_000, "lot"),
        ],
      },
      prefix,
    ),
    numbered(
      {
        id: "inv-sample",
        kind: "invoice",
        sequence: 1,
        date: "2026-08-15",
        dueDate: "2026-08-22",
        partyId: "pty-demo",
        vatRate: 0,
        status: "issued",
        number: "OFG-INV-20260815-61C38E",
        notes:
          "1. Payment is due by the date shown above.\n2. Pay through the Ofagros client portal. Pesapal processes the payment. This page is your record.\n3. Farm work starts after this invoice is marked paid.",
        items: [taxed("Coffee Estate Share", 1, 12_500_000, "lot", false)],
      },
      prefix,
    ),
    numbered(
      {
        id: "inv-2",
        kind: "invoice",
        sequence: 2,
        date: "2026-06-04",
        dueDate: "2026-06-18",
        partyId: "pty-nile",
        vatRate: 0.18,
        status: "issued",
        notes: "Net 14 days. LPO NCT/26/441.",
        items: [
          taxed("Washed arabica, FAQ, 60kg bags", 80, 980_000, "bags"),
          taxed("Transport to Kampala warehouse", 1, 1_200_000, "trip"),
        ],
      },
      prefix,
    ),
    numbered(
      {
        id: "inv-3",
        kind: "invoice",
        sequence: 3,
        date: "2026-08-29",
        dueDate: "2026-09-12",
        partyId: "pty-pearl",
        vatRate: 0.18,
        status: "issued",
        relatedDocumentId: "qtn-1",
        notes: "Breakfast blend supply, September.",
        items: [taxed("Breakfast blend, 1kg retail packs", 240, 28_000, "packs")],
      },
      prefix,
    ),
    numbered(
      {
        id: "inv-4",
        kind: "invoice",
        sequence: 4,
        date: "2026-03-20",
        dueDate: "2026-04-03",
        partyId: "pty-kituza",
        vatRate: 0.18,
        status: "issued",
        notes: "Overdue. Followed up 12 May and 3 August.",
        items: [taxed("Pruning and rejuvenation, 12 acres", 1, 6_800_000, "lot")],
      },
      prefix,
    ),
    numbered(
      {
        id: "rct-1",
        kind: "receipt",
        sequence: 1,
        date: "2026-06-20",
        partyId: "pty-nile",
        vatRate: 0,
        status: "issued",
        relatedDocumentId: "inv-2",
        paymentMethod: "bank-transfer",
        paymentRef: "STN-4412901",
        allocatedAmount: 20_000_000,
        notes: "Part payment, received with thanks.",
        items: [taxed("Payment on account", 1, 20_000_000, "lot", false)],
      },
      prefix,
    ),
    numbered(
      {
        id: "exp-1",
        kind: "expense",
        sequence: 1,
        date: "2026-08-02",
        partyId: "pty-seedco",
        vatRate: 0.18,
        status: "issued",
        expenseCategory: "Farm inputs",
        notes: "Supplier invoice SC/26/1902",
        items: [taxed("NPK 17:17:17, 50kg", 40, 148_000, "bags")],
      },
      prefix,
    ),
    numbered(
      {
        id: "exp-2",
        kind: "expense",
        sequence: 2,
        date: "2026-08-18",
        partyId: "pty-shell",
        vatRate: 0,
        status: "issued",
        expenseCategory: "Fuel",
        paymentMethod: "mobile-money",
        notes: "Field team, Mukono run",
        items: [taxed("Diesel", 1, 640_000, "lot", false)],
      },
      prefix,
    ),
    numbered(
      {
        id: "exp-3",
        kind: "expense",
        sequence: 3,
        date: "2026-07-01",
        partyId: "pty-seedco",
        vatRate: 0,
        status: "issued",
        expenseCategory: "Rent",
        items: [taxed("Store rent, Industrial Area, July", 1, 2_400_000, "month", false)],
      },
      prefix,
    ),
    numbered(
      {
        id: "ltr-1",
        kind: "letter",
        sequence: 1,
        date: "2026-09-01",
        partyId: "pty-nile",
        vatRate: 0,
        status: "issued",
        subject: "Demand for the balance on INV-2026-0002",
        body: "Dear Accounts,\n\nWe write in respect of our invoice for washed arabica delivered in June. We acknowledge your transfer of UGX 20,000,000 and kindly request settlement of the remaining balance within seven days.\n\nFarm collections for the new season depend on this account being current.\n\nYours faithfully,\n\nAccounts\nOfagros Limited",
        items: [],
      },
      prefix,
    ),
  ];

  return docs.map((doc) => {
    if (
      branding.plan !== "starter" &&
      (doc.kind === "invoice" || doc.kind === "receipt") &&
      doc.status === "issued"
    ) {
      return { ...doc, efris: makeEfris(branding, doc) };
    }
    return doc;
  });
}

export function seedState(): BooksState {
  const branding: BooksState["branding"] = {
    name: "Ofagros Limited",
    tagline: "Solutions for agriculture",
    tin: "1000890123",
    vatNo: "1000890123",
    address: "Kampala, Central Region, Uganda",
    city: "Kampala, Uganda",
    phone: "+256 788 141 342",
    email: "ofagrosltd@gmail.com",
    website: "www.ofagros.org",
    bankName: "Stanbic Bank Uganda",
    accountName: "Ofagros Limited",
    accountNumber: "9030008844211",
    brandColor: "#82B440",
    logoDataUrl: "/brand/ofagros-logo.png",
    prefix: "OFG",
    paymentNote: "Make payment to Ofagros Limited, Kampala.",
    invoiceComments:
      "1. Payment is due by the date shown above.\n2. Pay through the Ofagros client portal. Pesapal processes the payment. This page is your record.\n3. Farm work starts after this invoice is marked paid.",
    plan: "sme",
  };

  return {
    branding,
    parties,
    documents: seedDocuments(branding.prefix, branding),
  };
}
