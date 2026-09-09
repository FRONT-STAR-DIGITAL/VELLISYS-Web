"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import { KIND_META } from "@/lib/plans";
import { outstandingInvoice } from "@/lib/reports";
import { addDays, todayIso, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import type { DocumentRecord } from "@/lib/types";

export function DocActions({ doc }: { doc: DocumentRecord }) {
  const { state, voidDoc, issue } = useBooks();
  const router = useRouter();
  const [reason, setReason] = useState("");
  const share =
    typeof window !== "undefined" ? `${window.location.origin}/i/${doc.id}` : `/i/${doc.id}`;
  const party = state.parties.find((p) => p.id === doc.partyId);

  function printPdf() {
    window.print();
  }

  function emailClient() {
    const subject = encodeURIComponent(`${KIND_META[doc.kind].singular} ${doc.number} — ${state.branding.name}`);
    const body = encodeURIComponent(
      `Please find ${doc.number} from ${state.branding.name}.\n\nOpen or print:\n${share}\n\n${state.branding.phone}\n${state.branding.email}`,
    );
    window.location.href = `mailto:${party?.email ?? ""}?subject=${subject}&body=${body}`;
  }

  async function copyLink() {
    await navigator.clipboard.writeText(share);
  }

  function convert() {
    const created = issue({
      kind: "invoice",
      date: todayIso(),
      dueDate: addDays(todayIso(), 7),
      partyId: doc.partyId,
      items: doc.items,
      vatRate: doc.vatRate,
      notes: `From ${doc.number}.`,
      relatedDocumentId: doc.id,
    });
    router.push(`/app/doc/${created.id}`);
  }

  function receive() {
    const due = outstandingInvoice(state, doc);
    const created = issue({
      kind: "receipt",
      date: todayIso(),
      partyId: doc.partyId,
      items: [
        {
          description: `Payment on account — ${doc.number}`,
          qty: 1,
          unit: "lot",
          rate: due,
          taxed: false,
        },
      ],
      vatRate: 0,
      relatedDocumentId: doc.id,
      allocatedAmount: due,
      paymentMethod: "mobile-money",
      notes: `Received with thanks against ${doc.number}.`,
    });
    router.push(`/app/doc/${created.id}`);
  }

  return (
    <div className="no-print flex flex-wrap items-center gap-2">
      <Button onClick={printPdf}>Download / print PDF</Button>
      <Button variant="outline" onClick={emailClient}>
        Email client
      </Button>
      <Button variant="outline" onClick={copyLink}>
        Copy share link
      </Button>
      {doc.kind === "quotation" && doc.status !== "void" && (
        <Button variant="secondary" onClick={convert}>
          Convert to invoice
        </Button>
      )}
      {doc.kind === "invoice" &&
        doc.status !== "void" &&
        outstandingInvoice(state, doc) > 0 && (
          <Button variant="secondary" onClick={receive}>
            Receipt {ugx(outstandingInvoice(state, doc))}
          </Button>
        )}
      {doc.status !== "void" && (
        <span className="ml-auto flex items-center gap-2">
          <input
            className="h-8 rounded-lg border border-input px-2 text-sm"
            placeholder="Void reason"
            value={reason}
            onChange={(e) => setReason(e.target.value)}
          />
          <Button
            variant="destructive"
            disabled={!reason.trim()}
            onClick={() => voidDoc(doc.id, reason.trim())}
          >
            Void
          </Button>
        </span>
      )}
      <Button variant="ghost" render={<Link href={`/app/${KIND_META[doc.kind].slug}`} />}>
        Back
      </Button>
    </div>
  );
}
