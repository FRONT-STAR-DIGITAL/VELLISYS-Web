"use client";

import Link from "next/link";
import {
  cashBook,
  creditors,
  debtors,
  expenseTotal,
  expensesByCategory,
  incomeTotal,
  profit,
  vatInput,
  vatOutput,
} from "@/lib/reports";
import { formatDate, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import { partyById } from "@/lib/reports";

export default function ReportsPage() {
  const { state } = useBooks();
  const inc = incomeTotal(state);
  const exp = expenseTotal(state);
  const out = vatOutput(state);
  const inn = vatInput(state);
  const dbt = debtors(state);
  const crd = creditors(state);
  const cats = expensesByCategory(state);
  const cash = cashBook(state);

  return (
    <div className="mx-auto max-w-5xl space-y-10">
      <div className="flex items-end justify-between">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">Reports</h1>
          <p className="text-sm text-muted-foreground">
            The short pack: P&amp;L, VAT, debtors, expenses, cash book. Print any table from the
            browser.
          </p>
        </div>
        <button type="button" className="text-sm underline" onClick={() => window.print()}>
          Print / PDF
        </button>
      </div>

      <section>
        <h2 className="text-lg font-semibold">Profit and loss</h2>
        <table className="mt-3 w-full max-w-md text-sm">
          <tbody>
            <Line k="Income (invoices issued)" v={ugx(inc)} />
            <Line k="Expenses" v={ugx(exp)} />
            <Line k="Profit" v={ugx(profit(state))} strong />
          </tbody>
        </table>
      </section>

      <section>
        <h2 className="text-lg font-semibold">VAT worksheet</h2>
        <table className="mt-3 w-full max-w-md text-sm">
          <tbody>
            <Line k="Output VAT (sales)" v={ugx(out)} />
            <Line k="Input VAT (expenses)" v={ugx(inn)} />
            <Line k="Payable" v={ugx(Math.max(0, out - inn))} strong />
          </tbody>
        </table>
      </section>

      <Aging title="Debtors" rows={dbt} href="/app/invoices" />
      <Aging title="Creditors" rows={crd} href="/app/expenses" />

      <section>
        <h2 className="text-lg font-semibold">Expenses by category</h2>
        <table className="mt-3 w-full max-w-lg text-sm">
          <tbody>
            {cats.map((c) => (
              <Line key={c.category} k={c.category} v={ugx(c.amount)} />
            ))}
          </tbody>
        </table>
      </section>

      <section>
        <h2 className="text-lg font-semibold">Cash book</h2>
        <div className="mt-3 overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-[11px] uppercase text-muted-foreground">
                <th className="px-3 py-2">Date</th>
                <th className="px-3 py-2">Ref</th>
                <th className="px-3 py-2">Name</th>
                <th className="px-3 py-2 text-right">In</th>
                <th className="px-3 py-2 text-right">Out</th>
                <th className="px-3 py-2 text-right">Balance</th>
              </tr>
            </thead>
            <tbody>
              {cash.map((row) => (
                <tr key={row.doc.id} className="border-b last:border-0">
                  <td className="px-3 py-2">{formatDate(row.doc.date)}</td>
                  <td className="px-3 py-2 font-mono text-xs">
                    <Link href={`/app/doc/${row.doc.id}`} className="hover:underline">
                      {row.doc.number}
                    </Link>
                  </td>
                  <td className="px-3 py-2">{partyById(state, row.doc.partyId)?.name}</td>
                  <td className="px-3 py-2 text-right font-mono">{row.in ? ugx(row.in) : ""}</td>
                  <td className="px-3 py-2 text-right font-mono">{row.out ? ugx(row.out) : ""}</td>
                  <td className="px-3 py-2 text-right font-mono">{ugx(row.balance)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
}

function Line({ k, v, strong }: { k: string; v: string; strong?: boolean }) {
  return (
    <tr className={strong ? "font-semibold" : ""}>
      <td className="py-1.5">{k}</td>
      <td className="py-1.5 text-right font-mono">{v}</td>
    </tr>
  );
}

function Aging({
  title,
  rows,
  href,
}: {
  title: string;
  rows: ReturnType<typeof debtors>;
  href: string;
}) {
  return (
    <section>
      <h2 className="text-lg font-semibold">{title}</h2>
      {rows.length === 0 ? (
        <p className="mt-2 text-sm text-muted-foreground">None.</p>
      ) : (
        <div className="mt-3 overflow-x-auto rounded-lg border bg-card">
          <table className="w-full min-w-[640px] text-sm">
            <thead>
              <tr className="border-b text-left text-[11px] uppercase text-muted-foreground">
                <th className="px-3 py-2">Name</th>
                <th className="px-3 py-2 text-right">Current</th>
                <th className="px-3 py-2 text-right">31–60</th>
                <th className="px-3 py-2 text-right">61–90</th>
                <th className="px-3 py-2 text-right">90+</th>
                <th className="px-3 py-2 text-right">Total</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((row) => (
                <tr key={row.party.id} className="border-b last:border-0">
                  <td className="px-3 py-2">
                    <Link href={href} className="hover:underline">
                      {row.party.name}
                    </Link>
                  </td>
                  <td className="px-3 py-2 text-right font-mono">{ugx(row.current)}</td>
                  <td className="px-3 py-2 text-right font-mono">{ugx(row.days30)}</td>
                  <td className="px-3 py-2 text-right font-mono">{ugx(row.days60)}</td>
                  <td className="px-3 py-2 text-right font-mono">{ugx(row.days90)}</td>
                  <td className="px-3 py-2 text-right font-mono font-medium">{ugx(row.total)}</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </section>
  );
}
