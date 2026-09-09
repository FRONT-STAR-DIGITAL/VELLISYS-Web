"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import {
  collectedTotal,
  debtors,
  expenseTotal,
  incomeTotal,
  profit,
  vatInput,
  vatOutput,
} from "@/lib/reports";
import { ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";

export default function DeskPage() {
  const { state, resetDemo } = useBooks();
  const due = debtors(state).reduce((s, r) => s + r.total, 0);
  const vat = vatOutput(state) - vatInput(state);

  return (
    <div className="mx-auto max-w-5xl space-y-8">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <p className="text-xs font-medium uppercase tracking-widest text-muted-foreground">
            {state.branding.name}
          </p>
          <h1 className="text-3xl font-semibold tracking-tight">Desk</h1>
          <p className="mt-1 max-w-xl text-sm text-muted-foreground">
            Set your logo once. Issue a page. Send the PDF. This desk is seeded like the
            Ofagros invoice you shared.
          </p>
        </div>
        <div className="flex gap-2">
          <Button render={<Link href="/app/invoices/new" />}>New invoice</Button>
          <Button variant="outline" onClick={resetDemo}>
            Restore demo
          </Button>
        </div>
      </div>

      <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <Stat href="/app/invoices" label="Invoiced" value={ugx(incomeTotal(state))} />
        <Stat href="/app/receipts" label="Collected" value={ugx(collectedTotal(state))} />
        <Stat href="/app/reports" label="Debtors" value={ugx(due)} />
        <Stat href="/app/reports" label="Profit" value={ugx(profit(state))} />
      </section>

      <section className="grid gap-3 sm:grid-cols-3">
        <Stat href="/app/expenses" label="Expenses" value={ugx(expenseTotal(state))} />
        <Stat href="/app/reports" label="VAT payable" value={ugx(Math.max(0, vat))} />
        <Link href="/app/branding" className="rounded-lg border bg-card p-4">
          <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Branding</p>
          <div className="mt-3 flex items-center gap-3">
            {/* eslint-disable-next-line @next/next/no-img-element */}
            <img src={state.branding.logoDataUrl} alt="" className="h-8 w-auto" />
            <span
              className="size-6 rounded-full border"
              style={{ background: state.branding.brandColor }}
            />
          </div>
        </Link>
      </section>
    </div>
  );
}

function Stat({ href, label, value }: { href: string; label: string; value: string }) {
  return (
    <Link href={href} className="rounded-lg border bg-card p-4">
      <p className="text-[11px] uppercase tracking-wide text-muted-foreground">{label}</p>
      <p className="mt-2 font-mono text-lg">{value}</p>
    </Link>
  );
}
