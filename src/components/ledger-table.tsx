"use client";

import Link from "next/link";
import { ugx } from "@/lib/money";
import type { LedgerRow } from "@/lib/types";

export function LedgerTable({
  kind,
  rows,
}: {
  kind: "debtors" | "creditors";
  rows: LedgerRow[];
}) {
  const title = kind === "debtors" ? "Debtors book" : "Creditors book";
  const who = kind === "debtors" ? "customers" : "suppliers";
  const billed = kind === "debtors" ? "Invoiced" : "Billed";
  const collected = kind === "debtors" ? "Received" : "Paid";
  const empty =
    kind === "debtors"
      ? "Issue an invoice and a line appears here. You do not keep a second book."
      : "Record a supplier bill and a line appears here.";

  const totals = rows.reduce(
    (acc, row) => ({
      invoices: acc.invoices + row.invoices,
      paid: acc.paid + row.paid,
      outstanding: acc.outstanding + row.outstanding,
      current: acc.current + row.aging.current,
      days30: acc.days30 + row.aging.days30,
      days60: acc.days60 + row.aging.days60,
      days90: acc.days90 + row.aging.days90,
    }),
    {
      invoices: 0,
      paid: 0,
      outstanding: 0,
      current: 0,
      days30: 0,
      days60: 0,
      days90: 0,
    },
  );

  return (
    <div className="mx-auto max-w-6xl">
      <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
        Written from the counterfoils
      </p>
      <h1 className="font-heading text-3xl">{title}</h1>
      <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
        The paper debtors/creditors ledger was a third book nobody quite finished.
        Here it is a running total of the invoice and receipt (or bill and voucher)
        books — aged current / 30 / 60 / 90 days, in Uganda shillings.
      </p>

      {rows.length === 0 ? (
        <div className="mt-10 rounded-lg border border-dashed border-border px-6 py-16 text-center">
          <p className="font-heading text-xl">No {who} on the books yet</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">{empty}</p>
        </div>
      ) : (
        <div className="mt-6 overflow-x-auto rounded-lg border border-border bg-card">
          <table className="w-full min-w-[720px] text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-[0.12em] text-muted-foreground">
                <th className="px-3 py-2 font-medium">Name</th>
                <th className="px-3 py-2 font-medium text-right">{billed}</th>
                <th className="px-3 py-2 font-medium text-right">{collected}</th>
                <th className="px-3 py-2 font-medium text-right">Current</th>
                <th className="px-3 py-2 font-medium text-right">31–60</th>
                <th className="px-3 py-2 font-medium text-right">61–90</th>
                <th className="px-3 py-2 font-medium text-right">90+</th>
                <th className="px-3 py-2 font-medium text-right">Balance</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.party.id} className="border-b border-border/70 last:border-0">
                  <td className="px-3 py-2">
                    <Link
                      href={`/app/${kind}/${row.party.id}`}
                      className="font-medium hover:underline"
                    >
                      {row.party.name}
                    </Link>
                    {row.party.tin && (
                      <p className="font-mono text-[11px] text-muted-foreground">
                        TIN {row.party.tin}
                      </p>
                    )}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">
                    {ugx(row.invoices)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">
                    {ugx(row.paid)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">
                    {ugx(row.aging.current)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">
                    {ugx(row.aging.days30)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums">
                    {ugx(row.aging.days60)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums text-stamp">
                    {ugx(row.aging.days90)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono tabular-nums font-medium">
                    {ugx(row.outstanding)}
                  </td>
                </tr>
              ))}
            </tbody>
            <tfoot>
              <tr className="border-t border-border bg-muted/40 font-medium">
                <td className="px-3 py-2">Total</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.invoices)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.paid)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.current)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.days30)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.days60)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.days90)}</td>
                <td className="px-3 py-2 text-right font-mono">{ugx(totals.outstanding)}</td>
              </tr>
            </tfoot>
          </table>
        </div>
      )}
    </div>
  );
}
