"use client";

import { LedgerTable } from "@/components/ledger-table";
import { buildLedger } from "@/lib/ledgers";
import { useBooks } from "@/lib/store";

export default function CreditorsPage() {
  const { state } = useBooks();
  return <LedgerTable kind="creditors" rows={buildLedger(state, "creditors")} />;
}
