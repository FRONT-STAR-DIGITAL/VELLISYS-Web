"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { notFound } from "next/navigation";
import { Button } from "@/components/ui/button";
import { StatusChip } from "@/components/status-chip";
import { BOOK_META } from "@/lib/books";
import {
  documentTotal,
  documentsForParty,
  invoiceStatusLabel,
  outstandingOn,
  partyById,
} from "@/lib/ledgers";
import { formatDate, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";

export function PartyAccount({ kind }: { kind: "debtors" | "creditors" }) {
  const params = useParams<{ partyId: string }>();
  const { state } = useBooks();
  const party = partyById(state, params.partyId);
  if (!party) notFound();

  const books =
    kind === "debtors"
      ? (["quotation", "invoice", "receipt"] as const)
      : (["bill", "voucher"] as const);
  const docs = documentsForParty(state, party.id, [...books]);
  const openBook = kind === "debtors" ? "invoice" : "bill";
  const due = docs
    .filter((d) => d.book === openBook)
    .reduce((s, d) => s + outstandingOn(state, d), 0);

  return (
    <div className="mx-auto max-w-4xl">
      <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
        {kind === "debtors" ? "Debtor account" : "Creditor account"}
      </p>
      <h1 className="font-heading text-3xl">{party.name}</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        {party.address}
        {party.tin ? ` · TIN ${party.tin}` : ""}
        {party.phone ? ` · ${party.phone}` : ""}
      </p>
      <p className="mt-3 font-mono text-xl">
        {ugx(due)}{" "}
        <span className="text-sm font-sans text-muted-foreground">outstanding</span>
      </p>

      <div className="mt-4 flex flex-wrap gap-2">
        {kind === "debtors" ? (
          <>
            <Button render={<Link href="/app/books/invoices/new" />}>Issue invoice</Button>
            <Button variant="outline" render={<Link href="/app/books/receipts/new" />}>
              Issue receipt
            </Button>
          </>
        ) : (
          <>
            <Button render={<Link href="/app/books/bills/new" />}>Record bill</Button>
            <Button variant="outline" render={<Link href="/app/books/vouchers/new" />}>
              Issue voucher
            </Button>
          </>
        )}
      </div>

      {docs.length === 0 ? (
        <p className="mt-10 text-sm text-muted-foreground">No pages for this name yet.</p>
      ) : (
        <div className="mt-6 overflow-x-auto rounded-lg border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-[0.12em] text-muted-foreground">
                <th className="px-3 py-2 font-medium">Page</th>
                <th className="px-3 py-2 font-medium">Book</th>
                <th className="px-3 py-2 font-medium">Date</th>
                <th className="px-3 py-2 font-medium text-right">Amount</th>
                <th className="px-3 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {docs.map((doc) => (
                <tr key={doc.id} className="border-b border-border/70 last:border-0">
                  <td className="px-3 py-2 font-mono text-stamp">
                    <Link href={`/app/doc/${doc.id}`} className="hover:underline">
                      {doc.number}
                    </Link>
                  </td>
                  <td className="px-3 py-2">{BOOK_META[doc.book].short}</td>
                  <td className="px-3 py-2 text-muted-foreground">
                    {formatDate(doc.date)}
                  </td>
                  <td className="px-3 py-2 text-right font-mono">
                    {ugx(documentTotal(doc))}
                  </td>
                  <td className="px-3 py-2">
                    <StatusChip status={invoiceStatusLabel(state, doc)} />
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
