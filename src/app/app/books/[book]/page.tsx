"use client";

import Link from "next/link";
import { useParams } from "next/navigation";
import { notFound } from "next/navigation";
import { Button } from "@/components/ui/button";
import { StatusChip } from "@/components/status-chip";
import { BOOK_META, bookFromSlug } from "@/lib/books";
import {
  documentTotal,
  invoiceStatusLabel,
  outstandingOn,
  partyById,
} from "@/lib/ledgers";
import { formatDate, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";

export default function BookPage() {
  const params = useParams<{ book: string }>();
  const book = bookFromSlug(params.book);
  const { state } = useBooks();
  if (!book) {
    notFound();
  }
  const meta = BOOK_META[book];
  const docs = state.documents
    .filter((d) => d.book === book)
    .sort((a, b) => b.sequence - a.sequence);

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-wrap items-end justify-between gap-4">
        <div>
          <p className="text-[11px] uppercase tracking-[0.22em] text-stamp">
            Sequential book
          </p>
          <h1 className="font-heading text-3xl">{meta.title}</h1>
          <p className="mt-1 max-w-xl text-sm text-muted-foreground">{meta.blurb}</p>
        </div>
        <Button render={<Link href={`/app/books/${meta.slug}/new`} />}>
          {meta.verb}
        </Button>
      </div>

      {docs.length === 0 ? (
        <div className="mt-10 rounded-lg border border-dashed border-border bg-card/50 px-6 py-16 text-center">
          <p className="font-heading text-xl">The book is still empty</p>
          <p className="mx-auto mt-2 max-w-md text-sm text-muted-foreground">
            {meta.empty} The first page will be numbered{" "}
            <span className="font-mono text-stamp">{meta.prefix}-2026-0001</span>.
          </p>
          <Button className="mt-6" render={<Link href={`/app/books/${meta.slug}/new`} />}>
            {meta.verb}
          </Button>
        </div>
      ) : (
        <div className="mt-6 overflow-x-auto rounded-lg border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-[0.14em] text-muted-foreground">
                <th className="px-3 py-2 font-medium">No.</th>
                <th className="px-3 py-2 font-medium">Date</th>
                <th className="px-3 py-2 font-medium">Name</th>
                <th className="px-3 py-2 font-medium text-right">Amount</th>
                <th className="px-3 py-2 font-medium text-right">Outstanding</th>
                <th className="px-3 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {docs.map((doc) => {
                const party = partyById(state, doc.partyId);
                const status =
                  doc.book === "quotation" &&
                  state.documents.some(
                    (d) =>
                      d.book === "invoice" &&
                      d.relatedDocumentId === doc.id &&
                      d.status !== "void",
                  )
                    ? "invoiced"
                    : invoiceStatusLabel(state, doc);
                const due =
                  doc.book === "invoice" || doc.book === "bill"
                    ? outstandingOn(state, doc)
                    : 0;
                return (
                  <tr key={doc.id} className="border-b border-border/70 last:border-0">
                    <td className="px-3 py-2 font-mono text-stamp">
                      <Link href={`/app/doc/${doc.id}`} className="hover:underline">
                        {doc.number}
                      </Link>
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">
                      {formatDate(doc.date)}
                    </td>
                    <td className="px-3 py-2">{party?.name ?? "—"}</td>
                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                      {ugx(documentTotal(doc))}
                    </td>
                    <td className="px-3 py-2 text-right font-mono tabular-nums">
                      {doc.book === "invoice" || doc.book === "bill" ? ugx(due) : "—"}
                    </td>
                    <td className="px-3 py-2">
                      <StatusChip status={status} />
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      )}
    </div>
  );
}
