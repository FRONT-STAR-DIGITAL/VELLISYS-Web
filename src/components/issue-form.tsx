"use client";

import { useMemo, useState } from "react";
import { useRouter } from "next/navigation";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { EXPENSE_CATEGORIES, addDays, todayIso, ugx } from "@/lib/money";
import { KIND_META } from "@/lib/plans";
import { formatNumber, nextSequence, outstandingInvoice } from "@/lib/reports";
import { useBooks } from "@/lib/store";
import type { DocKind, LineItem, Party, PaymentMethod } from "@/lib/types";

export function IssueForm({
  kind,
  presetPartyId,
  presetRelatedId,
}: {
  kind: DocKind;
  presetPartyId?: string;
  presetRelatedId?: string;
}) {
  const { state, issue } = useBooks();
  const router = useRouter();
  const meta = KIND_META[kind];
  const customers = state.parties.filter((p) => p.kind !== "supplier");
  const suppliers = state.parties.filter((p) => p.kind !== "customer");
  const parties = kind === "expense" ? suppliers : customers;
  const efrisOn = state.branding.plan !== "starter" && (kind === "invoice" || kind === "receipt");

  const [date, setDate] = useState(todayIso());
  const [due, setDue] = useState(addDays(todayIso(), 7));
  const [partyId, setPartyId] = useState(presetPartyId ?? parties[0]?.id ?? "");
  const [newName, setNewName] = useState("");
  const [newPhone, setNewPhone] = useState("");
  const [newEmail, setNewEmail] = useState("");
  const [newAddress, setNewAddress] = useState("");
  const [adding, setAdding] = useState(false);
  const [description, setDescription] = useState("");
  const [amount, setAmount] = useState("");
  const [vat, setVat] = useState(kind === "invoice" || kind === "quotation" || kind === "expense");
  const [notes, setNotes] = useState(kind === "invoice" ? state.branding.invoiceComments : "");
  const [subject, setSubject] = useState("");
  const [body, setBody] = useState("");
  const [category, setCategory] = useState<string>(EXPENSE_CATEGORIES[0]);
  const [relatedId, setRelatedId] = useState(presetRelatedId ?? "");
  const [method, setMethod] = useState<PaymentMethod>("mobile-money");
  const [ref, setRef] = useState("");
  const [error, setError] = useState<string | null>(null);

  const openInvoices = useMemo(
    () =>
      state.documents.filter(
        (d) =>
          d.kind === "invoice" &&
          d.status !== "void" &&
          outstandingInvoice(state, d) > 0 &&
          (!partyId || d.partyId === partyId),
      ),
    [partyId, state],
  );

  const preview = formatNumber(
    state.branding.prefix,
    kind,
    date.slice(0, 4),
    nextSequence(state, kind),
  );

  function submit(e: React.FormEvent) {
    e.preventDefault();
    setError(null);
    const newParty: Omit<Party, "id"> | undefined =
      adding && newName.trim()
        ? {
            name: newName.trim(),
            kind: kind === "expense" ? "supplier" : "customer",
            phone: newPhone.trim() || undefined,
            email: newEmail.trim() || undefined,
            address: newAddress.trim() || undefined,
          }
        : undefined;
    if (!adding && !partyId) {
      setError("Choose who this is for.");
      return;
    }
    if (adding && !newName.trim()) {
      setError("Write the name as it should appear on the page.");
      return;
    }

    if (kind === "letter") {
      if (!subject.trim() || !body.trim()) {
        setError("A letter needs a subject and a body.");
        return;
      }
      const record = issue({
        kind,
        date,
        partyId,
        newParty,
        items: [],
        vatRate: 0,
        subject: subject.trim(),
        body: body.trim(),
      });
      router.push(`/app/doc/${record.id}`);
      return;
    }

    const value = Number(String(amount).replace(/,/g, ""));
    if (!Number.isFinite(value) || value <= 0) {
      setError("Enter an amount in Uganda shillings.");
      return;
    }
    if (!description.trim()) {
      setError("Write what this is for — one line is enough.");
      return;
    }

    const items: LineItem[] = [
      {
        description: description.trim(),
        qty: 1,
        unit: "lot",
        rate: Math.round(value),
        taxed: vat && kind !== "receipt",
      },
    ];
    const related = state.documents.find((d) => d.id === relatedId);
    const record = issue({
      kind,
      date,
      dueDate: kind === "invoice" || kind === "quotation" ? due : undefined,
      partyId: related?.partyId ?? partyId,
      newParty,
      items,
      vatRate: vat && kind !== "receipt" ? 0.18 : 0,
      notes: notes.trim() || undefined,
      relatedDocumentId: related?.id,
      paymentMethod: kind === "receipt" ? method : undefined,
      paymentRef: ref.trim() || undefined,
      allocatedAmount: kind === "receipt" ? Math.round(value) : undefined,
      expenseCategory: kind === "expense" ? category : undefined,
    });
    router.push(`/app/doc/${record.id}`);
  }

  return (
    <form onSubmit={submit} className="mx-auto max-w-xl space-y-5">
      <p className="text-sm text-muted-foreground">
        {preview} · {efrisOn ? "EFRIS mark will be added" : "Commercial PDF, no fiscal mark"}
      </p>

      <div className="grid gap-3 sm:grid-cols-2">
        <Field label="Date">
          <Input type="date" value={date} onChange={(e) => setDate(e.target.value)} />
        </Field>
        {(kind === "invoice" || kind === "quotation") && (
          <Field label={kind === "quotation" ? "Valid until" : "Due date"}>
            <Input type="date" value={due} onChange={(e) => setDue(e.target.value)} />
          </Field>
        )}
      </div>

      <div>
        <div className="mb-1 flex items-center justify-between">
          <Label>{kind === "expense" ? "Supplier" : "Client"}</Label>
          <button
            type="button"
            className="text-xs text-primary underline-offset-2 hover:underline"
            onClick={() => setAdding((v) => !v)}
          >
            {adding ? "Choose existing" : "New name"}
          </button>
        </div>
        {adding ? (
          <div className="grid gap-2">
            <Input placeholder="Name on the document" value={newName} onChange={(e) => setNewName(e.target.value)} />
            <Input placeholder="Phone" value={newPhone} onChange={(e) => setNewPhone(e.target.value)} />
            <Input placeholder="Email" value={newEmail} onChange={(e) => setNewEmail(e.target.value)} />
            <Input placeholder="Address" value={newAddress} onChange={(e) => setNewAddress(e.target.value)} />
          </div>
        ) : (
          <select
            className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
            value={partyId}
            onChange={(e) => setPartyId(e.target.value)}
          >
            {parties.map((p) => (
              <option key={p.id} value={p.id}>
                {p.name}
              </option>
            ))}
          </select>
        )}
      </div>

      {kind === "receipt" && (
        <Field label="Against invoice (optional)">
          <select
            className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
            value={relatedId}
            onChange={(e) => {
              setRelatedId(e.target.value);
              const d = state.documents.find((x) => x.id === e.target.value);
              if (d) {
                setAmount(String(outstandingInvoice(state, d)));
                setDescription(`Payment on account — ${d.number}`);
                setPartyId(d.partyId);
              }
            }}
          >
            <option value="">On account</option>
            {openInvoices.map((d) => (
              <option key={d.id} value={d.id}>
                {d.number} · {ugx(outstandingInvoice(state, d))} due
              </option>
            ))}
          </select>
        </Field>
      )}

      {kind === "letter" ? (
        <>
          <Field label="Subject">
            <Input value={subject} onChange={(e) => setSubject(e.target.value)} />
          </Field>
          <Field label="Letter">
            <Textarea rows={10} value={body} onChange={(e) => setBody(e.target.value)} />
          </Field>
        </>
      ) : (
        <>
          <Field label="What is this for">
            <Input
              placeholder="Coffee Estate Share"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
            />
          </Field>
          <Field label="Amount (UGX)">
            <Input
              inputMode="numeric"
              className="font-mono"
              placeholder="12500000"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
            />
          </Field>
          {kind !== "receipt" && (
            <label className="flex items-center gap-2 text-sm">
              <input type="checkbox" checked={vat} onChange={(e) => setVat(e.target.checked)} />
              VAT 18%
            </label>
          )}
          {kind === "expense" && (
            <Field label="Category">
              <select
                className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
                value={category}
                onChange={(e) => setCategory(e.target.value)}
              >
                {EXPENSE_CATEGORIES.map((c) => (
                  <option key={c}>{c}</option>
                ))}
              </select>
            </Field>
          )}
          {kind === "receipt" && (
            <div className="grid gap-3 sm:grid-cols-2">
              <Field label="How paid">
                <select
                  className="h-8 w-full rounded-lg border border-input bg-transparent px-2.5 text-sm"
                  value={method}
                  onChange={(e) => setMethod(e.target.value as PaymentMethod)}
                >
                  <option value="mobile-money">Mobile money</option>
                  <option value="bank-transfer">Bank transfer</option>
                  <option value="pesapal">Pesapal / card</option>
                  <option value="cash">Cash</option>
                  <option value="cheque">Cheque</option>
                </select>
              </Field>
              <Field label="Reference">
                <Input value={ref} onChange={(e) => setRef(e.target.value)} />
              </Field>
            </div>
          )}
          <Field label="Comments on the page">
            <Textarea rows={4} value={notes} onChange={(e) => setNotes(e.target.value)} />
          </Field>
        </>
      )}

      {error && <p className="text-sm text-destructive">{error}</p>}

      <Button type="submit" size="lg">
        {meta.verb} and open PDF
      </Button>
    </form>
  );
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <Label className="mb-1">{label}</Label>
      {children}
    </div>
  );
}
