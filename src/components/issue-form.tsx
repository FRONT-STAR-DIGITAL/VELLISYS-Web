"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Plus, Trash2 } from "lucide-react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { BOOK_META, PAYMENT_METHODS, formatBookNumber } from "@/lib/books";
import {
  documentTotal,
  nextSequence,
  outstandingOn,
  partyById,
} from "@/lib/ledgers";
import { grandTotal, todayIso, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import type { BookType, LineItem, PartyKind, PaymentMethod } from "@/lib/types";

const emptyItem = (): LineItem => ({
  description: "",
  qty: 1,
  unit: "pcs",
  rate: 0,
});

export function IssueForm({
  book,
  presetPartyId,
  presetRelatedId,
}: {
  book: BookType;
  presetPartyId?: string;
  presetRelatedId?: string;
}) {
  const { state, issue } = useBooks();
  const router = useRouter();
  const meta = BOOK_META[book];
  const isMoneyInOut = book === "receipt" || book === "voucher";
  const sourceBook: BookType = book === "receipt" ? "invoice" : "bill";

  const parties = state.parties.filter(
    (p) => p.kind === meta.partyRole || p.kind === "both",
  );

  const [date, setDate] = useState(todayIso());
  const [partyId, setPartyId] = useState(presetPartyId ?? parties[0]?.id ?? "");
  const [addingParty, setAddingParty] = useState(false);
  const [newName, setNewName] = useState("");
  const [newTin, setNewTin] = useState("");
  const [newAddress, setNewAddress] = useState("");
  const [newPhone, setNewPhone] = useState("");
  const [items, setItems] = useState<LineItem[]>([emptyItem()]);
  const [vatRate, setVatRate] = useState(isMoneyInOut ? 0 : 0.18);
  const [notes, setNotes] = useState("");
  const [relatedId, setRelatedId] = useState(presetRelatedId ?? "");
  const [amount, setAmount] = useState("");
  const [method, setMethod] = useState<PaymentMethod>("bank-transfer");
  const [ref, setRef] = useState("");
  const [error, setError] = useState<string | null>(null);

  const sequence = nextSequence(state, book);
  const previewNumber = formatBookNumber(book, state.company.booksYear, sequence);

  const openDocs = useMemo(() => {
    if (!isMoneyInOut || !partyId) return [];
    return state.documents.filter(
      (d) =>
        d.book === sourceBook &&
        d.partyId === partyId &&
        d.status !== "void" &&
        outstandingOn(state, d) > 0,
    );
  }, [isMoneyInOut, partyId, sourceBook, state]);

  const related = state.documents.find((d) => d.id === relatedId);
  const relatedDue = related ? outstandingOn(state, related) : 0;

  function updateItem(index: number, patch: Partial<LineItem>) {
    setItems((rows) => rows.map((row, i) => (i === index ? { ...row, ...patch } : row)));
  }

  function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);

    const kind: PartyKind = meta.partyRole;
    const newParty =
      addingParty && newName.trim()
        ? {
            name: newName.trim(),
            kind,
            tin: newTin.trim() || undefined,
            address: newAddress.trim() || undefined,
            phone: newPhone.trim() || undefined,
          }
        : undefined;

    if (!addingParty && !partyId) {
      setError("Choose who this page is for.");
      return;
    }
    if (addingParty && !newName.trim()) {
      setError("Write the name as it should appear on the page.");
      return;
    }

    if (isMoneyInOut) {
      const value = Number(amount.replace(/,/g, ""));
      if (!Number.isFinite(value) || value <= 0) {
        setError("Enter an amount in Uganda shillings.");
        return;
      }
      const description = related
        ? `Payment on account — ${related.number}`
        : "Payment on account";
      const record = issue({
        book,
        date,
        partyId,
        newParty,
        items: [{ description, qty: 1, unit: "lot", rate: Math.round(value) }],
        vatRate: 0,
        notes: notes.trim() || undefined,
        relatedDocumentId: related?.id,
        paymentMethod: method,
        paymentRef: ref.trim() || undefined,
        allocations:
          related && Math.round(value) > 0
            ? [{ documentId: related.id, amount: Math.round(value) }]
            : undefined,
      });
      router.push(`/app/doc/${record.id}`);
      return;
    }

    const clean = items.filter((i) => i.description.trim() && i.qty > 0 && i.rate > 0);
    if (clean.length === 0) {
      setError("Add at least one line — description, quantity and rate.");
      return;
    }

    const record = issue({
      book,
      date,
      partyId,
      newParty,
      items: clean,
      vatRate,
      notes: notes.trim() || undefined,
      relatedDocumentId: related?.id,
    });
    router.push(`/app/doc/${record.id}`);
  }

  const liveTotal = isMoneyInOut
    ? Number(amount.replace(/,/g, "")) || 0
    : grandTotal(items, vatRate);

  return (
    <form onSubmit={submit} className="mx-auto max-w-3xl space-y-6">
      <div className="flex flex-wrap items-end justify-between gap-3 border-b border-border pb-4">
        <div>
          <p className="text-[11px] uppercase tracking-[0.2em] text-stamp">
            Next number locked
          </p>
          <p className="font-mono text-2xl font-semibold text-stamp">{previewNumber}</p>
          <p className="mt-1 max-w-md text-sm text-muted-foreground">
            Once issued, this number stays in the book. Void a spoiled page — never skip it.
          </p>
        </div>
        <div className="text-right">
          <Label htmlFor="date">Date</Label>
          <Input
            id="date"
            type="date"
            value={date}
            onChange={(e) => setDate(e.target.value)}
            className="mt-1 w-[11.5rem]"
          />
        </div>
      </div>

      <div className="grid gap-4 sm:grid-cols-2">
        <div className="sm:col-span-2">
          <div className="flex items-center justify-between gap-2">
            <Label htmlFor="party">
              {meta.partyRole === "customer" ? "Customer" : "Supplier"}
            </Label>
            <button
              type="button"
              className="text-xs text-primary underline-offset-2 hover:underline"
              onClick={() => setAddingParty((v) => !v)}
            >
              {addingParty ? "Choose existing" : "New name in the book"}
            </button>
          </div>
          {addingParty ? (
            <div className="mt-2 grid gap-2 sm:grid-cols-2">
              <Input
                placeholder="Name as on the rubber stamp"
                value={newName}
                onChange={(e) => setNewName(e.target.value)}
              />
              <Input
                placeholder="TIN (optional)"
                value={newTin}
                onChange={(e) => setNewTin(e.target.value)}
              />
              <Input
                placeholder="Address"
                value={newAddress}
                onChange={(e) => setNewAddress(e.target.value)}
              />
              <Input
                placeholder="Phone"
                value={newPhone}
                onChange={(e) => setNewPhone(e.target.value)}
              />
            </div>
          ) : (
            <select
              id="party"
              className="mt-1 h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
              value={partyId}
              onChange={(e) => {
                setPartyId(e.target.value);
                setRelatedId("");
              }}
            >
              {parties.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name}
                </option>
              ))}
            </select>
          )}
        </div>

        {isMoneyInOut && (
          <>
            <div className="sm:col-span-2">
              <Label htmlFor="related">
                Allocate to {book === "receipt" ? "invoice" : "bill"}
              </Label>
              <select
                id="related"
                className="mt-1 h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
                value={relatedId}
                onChange={(e) => {
                  setRelatedId(e.target.value);
                  const doc = state.documents.find((d) => d.id === e.target.value);
                  if (doc) setAmount(String(outstandingOn(state, doc)));
                }}
              >
                <option value="">On account (unallocated)</option>
                {openDocs.map((d) => (
                  <option key={d.id} value={d.id}>
                    {d.number} · {ugx(outstandingOn(state, d))} outstanding ·{" "}
                    {partyById(state, d.partyId)?.name}
                  </option>
                ))}
              </select>
              {related && (
                <p className="mt-1 text-xs text-muted-foreground">
                  {related.number} total {ugx(documentTotal(related))}, still due{" "}
                  {ugx(relatedDue)}.
                </p>
              )}
            </div>
            <div>
              <Label htmlFor="amount">Amount (UGX)</Label>
              <Input
                id="amount"
                inputMode="numeric"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
                className="mt-1 font-mono"
                placeholder="0"
              />
            </div>
            <div>
              <Label htmlFor="method">How paid</Label>
              <select
                id="method"
                className="mt-1 h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
                value={method}
                onChange={(e) => setMethod(e.target.value as PaymentMethod)}
              >
                {PAYMENT_METHODS.map((m) => (
                  <option key={m.value} value={m.value}>
                    {m.label}
                  </option>
                ))}
              </select>
            </div>
            <div className="sm:col-span-2">
              <Label htmlFor="ref">Cheque / MoMo / transfer reference</Label>
              <Input
                id="ref"
                value={ref}
                onChange={(e) => setRef(e.target.value)}
                className="mt-1"
                placeholder="e.g. STN CHQ 001448 or MTN 25677…"
              />
            </div>
          </>
        )}
      </div>

      {!isMoneyInOut && (
        <div>
          <div className="mb-2 flex items-center justify-between">
            <Label>Particulars</Label>
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setItems((rows) => [...rows, emptyItem()])}
            >
              <Plus data-icon="inline-start" />
              Line
            </Button>
          </div>
          <div className="space-y-2">
            {items.map((item, i) => (
              <div
                key={i}
                className="grid grid-cols-12 items-end gap-2 rounded-lg border border-border bg-card p-2"
              >
                <div className="col-span-12 sm:col-span-5">
                  <Input
                    placeholder="Description"
                    value={item.description}
                    onChange={(e) => updateItem(i, { description: e.target.value })}
                  />
                </div>
                <div className="col-span-4 sm:col-span-2">
                  <Input
                    type="number"
                    min={0}
                    value={item.qty}
                    onChange={(e) => updateItem(i, { qty: Number(e.target.value) })}
                  />
                </div>
                <div className="col-span-4 sm:col-span-2">
                  <Input
                    placeholder="unit"
                    value={item.unit}
                    onChange={(e) => updateItem(i, { unit: e.target.value })}
                  />
                </div>
                <div className="col-span-4 sm:col-span-2">
                  <Input
                    type="number"
                    min={0}
                    value={item.rate || ""}
                    onChange={(e) => updateItem(i, { rate: Number(e.target.value) })}
                    placeholder="Rate"
                  />
                </div>
                <div className="col-span-12 flex items-center justify-between sm:col-span-1 sm:justify-end">
                  <span className="font-mono text-xs text-muted-foreground sm:hidden">
                    {ugx(item.qty * item.rate)}
                  </span>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    onClick={() =>
                      setItems((rows) =>
                        rows.length === 1 ? [emptyItem()] : rows.filter((_, j) => j !== i),
                      )
                    }
                  >
                    <Trash2 />
                  </Button>
                </div>
              </div>
            ))}
          </div>
          <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
            <label className="flex items-center gap-2 text-sm">
              <input
                type="checkbox"
                checked={vatRate === 0.18}
                onChange={(e) => setVatRate(e.target.checked ? 0.18 : 0)}
              />
              VAT 18%
            </label>
            <p className="font-mono text-sm font-medium">{ugx(liveTotal)}</p>
          </div>
        </div>
      )}

      <div>
        <Label htmlFor="notes">Remarks</Label>
        <Textarea
          id="notes"
          value={notes}
          onChange={(e) => setNotes(e.target.value)}
          placeholder="LPO number, delivery note, warrant, validity…"
          className="mt-1"
        />
      </div>

      {error && <p className="text-sm text-destructive">{error}</p>}

      <div className="flex flex-wrap items-center gap-2">
        <Button type="submit" size="lg">
          {meta.verb} {previewNumber}
        </Button>
        <p className="text-xs text-muted-foreground">
          Original, duplicate and counterfoil are written together.
        </p>
      </div>
    </form>
  );
}
