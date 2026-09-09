"use client";

import { notFound, useParams } from "next/navigation";
import { BrandedSheet } from "@/components/branded-sheet";
import { DocActions } from "@/components/doc-actions";
import { partyById } from "@/lib/reports";
import { useBooks } from "@/lib/store";

export default function DocPage() {
  const { id } = useParams<{ id: string }>();
  const { state } = useBooks();
  const doc = state.documents.find((d) => d.id === id);
  if (!doc) notFound();
  const party = partyById(state, doc.partyId);

  return (
    <div className="space-y-4">
      <DocActions doc={doc} />
      <div className="overflow-auto rounded-lg border bg-neutral-200 p-4 print:overflow-visible print:rounded-none print:border-0 print:bg-white print:p-0">
        <BrandedSheet branding={state.branding} doc={doc} party={party} />
      </div>
    </div>
  );
}
