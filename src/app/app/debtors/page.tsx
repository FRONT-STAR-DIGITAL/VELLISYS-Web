"use client";

import { LedgerTable } from "@/components/ledger-table";
import { buildLedger } from "@/lib/ledgers";
import { useBooks } from "@/lib/store";

export default function DebtorsPage() {
  const { state } = useBooks();
  return <LedgerTable kind="debtors" rows={buildLedger(state, "debtors")} />;
}
