"use client";

import { useParams } from "next/navigation";
import { notFound } from "next/navigation";
import { DocumentActions } from "@/components/document-actions";
import { StationeryDeck } from "@/components/stationery";
import { partyById } from "@/lib/ledgers";
import { useBooks } from "@/lib/store";

export default function DocumentPage() {
  const params = useParams<{ id: string }>();
  const { state } = useBooks();
  const doc = state.documents.find((d) => d.id === params.id);
  if (!doc) notFound();
  const party = partyById(state, doc.partyId);

  return (
    <div className="mx-auto max-w-3xl space-y-5">
      <DocumentActions doc={doc} />
      <StationeryDeck doc={doc} party={party} />
    </div>
  );
}
