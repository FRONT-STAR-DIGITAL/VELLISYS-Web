"use client";

import { notFound, useParams } from "next/navigation";
import { BrandedSheet } from "@/components/branded-sheet";
import { partyById } from "@/lib/reports";
import { useBooks } from "@/lib/store";

export default function PublicInvoicePage() {
  const { id } = useParams<{ id: string }>();
  const { state } = useBooks();
  const doc = state.documents.find((d) => d.id === id);
  if (!doc) notFound();
  const party = partyById(state, doc.partyId);

  return (
    <div className="min-h-svh bg-neutral-200 py-6 print:bg-white print:py-0">
      <div className="no-print mx-auto mb-4 flex max-w-[210mm] justify-end px-4">
        <button
          type="button"
          className="rounded-md bg-black px-3 py-1.5 text-sm text-white"
          onClick={() => window.print()}
        >
          Download PDF
        </button>
      </div>
      <BrandedSheet branding={state.branding} doc={doc} party={party} />
    </div>
  );
}
