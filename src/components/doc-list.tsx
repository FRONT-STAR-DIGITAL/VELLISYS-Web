"use client";

import Link from "next/link";
import { Button } from "@/components/ui/button";
import { Badge } from "@/components/ui/badge";
import { IssueForm } from "@/components/issue-form";
import { KIND_META } from "@/lib/plans";
import { docTotal, invoiceLabel, outstandingInvoice, partyById } from "@/lib/reports";
import { formatDate, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import type { DocKind } from "@/lib/types";

export function DocList({ kind }: { kind: DocKind }) {
  const { state } = useBooks();
  const meta = KIND_META[kind];
  const docs = state.documents
    .filter((d) => d.kind === kind)
    .sort((a, b) => b.date.localeCompare(a.date) || b.sequence - a.sequence);

  return (
    <div className="mx-auto max-w-5xl">
      <div className="flex flex-wrap items-end justify-between gap-3">
        <div>
          <h1 className="text-2xl font-semibold tracking-tight">{meta.title}</h1>
          <p className="text-sm text-muted-foreground">
            {kind === "letter"
              ? "Headed paper in your logo and colour."
              : "Each page uses the branding you set. Send the PDF to the client."}
          </p>
        </div>
        <Button render={<Link href={`/app/${meta.slug}/new`} />}>{meta.verb}</Button>
      </div>

      {docs.length === 0 ? (
        <div className="mt-10 rounded-lg border border-dashed px-6 py-16 text-center">
          <p className="font-medium">Nothing here yet</p>
          <Button className="mt-4" render={<Link href={`/app/${meta.slug}/new`} />}>
            {meta.verb}
          </Button>
        </div>
      ) : (
        <div className="mt-6 overflow-x-auto rounded-lg border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="px-3 py-2 font-medium">Number</th>
                <th className="px-3 py-2 font-medium">Date</th>
                <th className="px-3 py-2 font-medium">Name</th>
                <th className="px-3 py-2 font-medium text-right">Amount</th>
                <th className="px-3 py-2 font-medium">Status</th>
              </tr>
            </thead>
            <tbody>
              {docs.map((doc) => {
                const party = partyById(state, doc.partyId);
                const status = invoiceLabel(state, doc);
                return (
                  <tr key={doc.id} className="border-b last:border-0">
                    <td className="px-3 py-2 font-mono text-xs">
                      <Link href={`/app/doc/${doc.id}`} className="hover:underline">
                        {doc.number}
                      </Link>
                    </td>
                    <td className="px-3 py-2 text-muted-foreground">{formatDate(doc.date)}</td>
                    <td className="px-3 py-2">
                      {party?.name ?? "—"}
                      {doc.subject ? (
                        <span className="block text-xs text-muted-foreground">{doc.subject}</span>
                      ) : null}
                    </td>
                    <td className="px-3 py-2 text-right font-mono">
                      {kind === "letter"
                        ? "—"
                        : kind === "invoice"
                          ? ugx(outstandingInvoice(state, doc) || docTotal(doc))
                          : ugx(docTotal(doc))}
                    </td>
                    <td className="px-3 py-2">
                      <Badge variant="secondary">{status}</Badge>
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

export function NewDoc({ kind }: { kind: DocKind }) {
  const meta = KIND_META[kind];
  return (
    <div className="mx-auto max-w-xl">
      <h1 className="text-2xl font-semibold tracking-tight">{meta.verb}</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        Name, what it is for, amount, due date. Branding is already on the page.
      </p>
      <div className="mt-6">
        <IssueForm kind={kind} />
      </div>
    </div>
  );
}
