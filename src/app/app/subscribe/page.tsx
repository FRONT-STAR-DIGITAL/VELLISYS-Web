"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import { useBooks } from "@/lib/store";

const PLANS = [
  {
    name: "Sole trader",
    price: "UGX 180,000",
    period: "a year",
    note: "About twelve receipt books from the stationer — except these never run out.",
    points: [
      "Four books: quotations, invoices, receipts, vouchers",
      "One cashier, one set of numbers",
      "Debtors and creditors ledgers",
      "Print original copies on ordinary paper",
    ],
  },
  {
    name: "SME",
    price: "UGX 480,000",
    period: "a year",
    note: "The accounts clerk’s desk, without a second ledger nobody updates.",
    featured: true,
    points: [
      "All five books, including supplier bills",
      "Five people on the same pad",
      "Aged debtors and creditors",
      "Voided pages stay in sequence",
    ],
  },
  {
    name: "Corporate",
    price: "UGX 1,440,000",
    period: "a year",
    note: "For the company that already buys books by the carton.",
    points: [
      "Branches with their own number series",
      "Cashier and accountant roles",
      "Year-end export of the counterfoils",
      "Roadmap: eFRIS fiscal copies, not a replacement for the books",
    ],
  },
];

export default function SubscribePage() {
  const { state } = useBooks();

  return (
    <div className="mx-auto max-w-5xl">
      <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
        Annual books · {state.company.booksYear}
      </p>
      <h1 className="font-heading text-3xl sm:text-4xl">
        You used to buy the books.
        <br />
        Now you subscribe to the year.
      </h1>
      <p className="mt-3 max-w-2xl text-muted-foreground">
        Stationers on Luwum Street still sell pads of 100. This is the same purchase,
        once a year: a locked number series, carbon copies, and ledgers that the
        paper books never kept for you. This demo does not take payment — it shows
        the offer.
      </p>

      <div className="mt-8 grid gap-4 lg:grid-cols-3">
        {PLANS.map((plan) => (
          <article
            key={plan.name}
            className={`flex flex-col rounded-lg border bg-card p-5 shadow-sm ${
              plan.featured ? "border-primary ring-1 ring-primary/20" : "border-border"
            }`}
          >
            <p className="font-heading text-xl">{plan.name}</p>
            <p className="mt-2 font-mono text-2xl">{plan.price}</p>
            <p className="text-xs text-muted-foreground">{plan.period}</p>
            <p className="mt-3 text-sm text-muted-foreground">{plan.note}</p>
            <ul className="mt-4 flex-1 space-y-2 text-sm">
              {plan.points.map((p) => (
                <li key={p} className="flex gap-2">
                  <span className="mt-1.5 size-1.5 shrink-0 rounded-full bg-stamp" />
                  {p}
                </li>
              ))}
            </ul>
            <Button className="mt-6" variant={plan.featured ? "default" : "outline"} disabled>
              {plan.featured ? "Current on this desk" : "Not in this demo"}
            </Button>
          </article>
        ))}
      </div>

      <p className="mt-8 text-sm text-muted-foreground">
        Back to{" "}
        <Link href="/app" className="text-primary underline-offset-2 hover:underline">
          the desk
        </Link>
        .
      </p>
    </div>
  );
}
