"use client";

import Link from "next/link";
import { BOOK_META, BOOK_ORDER } from "@/lib/books";
import {
  bookTotals,
  buildLedger,
  pagesUsed,
} from "@/lib/ledgers";
import { ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import { Button } from "@/components/ui/button";

export default function DeskPage() {
  const { state, resetDemo } = useBooks();
  const debtors = buildLedger(state, "debtors");
  const creditors = buildLedger(state, "creditors");
  const debtorsOut = debtors.reduce((s, r) => s + r.outstanding, 0);
  const creditorsOut = creditors.reduce((s, r) => s + r.outstanding, 0);
  const overdue = debtors.reduce((s, r) => s + r.aging.days90, 0);

  return (
    <div className="mx-auto max-w-5xl space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
            Books of {state.company.booksYear}
          </p>
          <h1 className="font-heading text-3xl sm:text-4xl">{state.company.name}</h1>
          <p className="mt-1 text-sm text-muted-foreground">
            {state.company.address}, {state.company.city} · TIN {state.company.tin}
          </p>
        </div>
        <Button variant="outline" size="sm" onClick={resetDemo}>
          Restore demo books
        </Button>
      </div>

      <section className="grid gap-3 sm:grid-cols-3">
        <Stat
          label="Debtors outstanding"
          value={ugx(debtorsOut)}
          hint={`${debtors.filter((d) => d.outstanding > 0).length} accounts`}
          href="/app/debtors"
        />
        <Stat
          label="Creditors outstanding"
          value={ugx(creditorsOut)}
          hint={`${creditors.filter((c) => c.outstanding > 0).length} suppliers`}
          href="/app/creditors"
        />
        <Stat
          label="Past 90 days"
          value={ugx(overdue)}
          hint="Still sitting in the debtors book"
          href="/app/debtors"
          warn
        />
      </section>

      <section>
        <h2 className="font-heading text-xl">This year’s books</h2>
        <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
          Same covers the stationer sold you — quotation, invoice, official receipt,
          supplier bills, payment vouchers. Fill a page. The ledgers write themselves.
        </p>
        <ul className="mt-4 grid gap-3 sm:grid-cols-2">
          {BOOK_ORDER.map((book) => {
            const meta = BOOK_META[book];
            const used = pagesUsed(state, book);
            const totals = bookTotals(state, book);
            return (
              <li key={book}>
                <Link
                  href={`/app/books/${meta.slug}`}
                  className="flex h-full flex-col rounded-lg border border-border bg-card p-4 shadow-sm transition hover:border-primary/40"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div>
                      <p className="font-heading text-lg">{meta.title}</p>
                      <p className="mt-1 text-sm text-muted-foreground">{meta.blurb}</p>
                    </div>
                    <p className="font-mono text-xs text-stamp">{meta.prefix}-2026</p>
                  </div>
                  <div className="mt-4 flex items-end justify-between text-sm">
                    <span className="text-muted-foreground">{used} pages used</span>
                    <span className="font-mono">{ugx(totals.issued)}</span>
                  </div>
                  <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted">
                    <div
                      className="h-full bg-primary"
                      style={{ width: `${Math.min(100, (used / 100) * 100)}%` }}
                    />
                  </div>
                  <p className="mt-1 text-[11px] text-muted-foreground">
                    Book of 100 · {Math.max(0, 100 - used)} leaves remaining this pad
                  </p>
                </Link>
              </li>
            );
          })}
        </ul>
      </section>
    </div>
  );
}

function Stat({
  label,
  value,
  hint,
  href,
  warn,
}: {
  label: string;
  value: string;
  hint: string;
  href: string;
  warn?: boolean;
}) {
  return (
    <Link
      href={href}
      className="rounded-lg border border-border bg-card p-4 shadow-sm"
    >
      <p className="text-[11px] uppercase tracking-[0.16em] text-muted-foreground">
        {label}
      </p>
      <p className={`mt-2 font-mono text-xl ${warn ? "text-stamp" : ""}`}>{value}</p>
      <p className="mt-1 text-xs text-muted-foreground">{hint}</p>
    </Link>
  );
}
