"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useState } from "react";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { BOOK_META, PAYMENT_METHODS } from "@/lib/books";
import { invoiceStatusLabel, outstandingOn } from "@/lib/ledgers";
import { todayIso, ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import type { DocumentRecord, PaymentMethod } from "@/lib/types";

export function DocumentActions({ doc }: { doc: DocumentRecord }) {
  const {
    state,
    voidDocument,
    convertQuotation,
    receiveAgainst,
    payAgainst,
  } = useBooks();
  const router = useRouter();
  const status = invoiceStatusLabel(state, doc);
  const due = outstandingOn(state, doc);

  const [voidOpen, setVoidOpen] = useState(false);
  const [reason, setReason] = useState("");
  const [payOpen, setPayOpen] = useState(false);
  const [amount, setAmount] = useState(due ? String(due) : "");
  const [method, setMethod] = useState<PaymentMethod>("bank-transfer");
  const [ref, setRef] = useState("");
  const [date, setDate] = useState(todayIso());

  const converted = state.documents.find(
    (d) => d.book === "invoice" && d.relatedDocumentId === doc.id && d.status !== "void",
  );

  function printPage() {
    window.print();
  }

  function onVoid() {
    if (!reason.trim()) return;
    voidDocument(doc.id, reason.trim());
    setVoidOpen(false);
  }

  function onConvert() {
    const invoice = convertQuotation(doc.id, todayIso());
    if (invoice) router.push(`/app/doc/${invoice.id}`);
  }

  function onPay() {
    const value = Number(amount.replace(/,/g, ""));
    if (!Number.isFinite(value) || value <= 0) return;
    const created =
      doc.book === "invoice"
        ? receiveAgainst(doc.id, value, date, method, ref)
        : payAgainst(doc.id, value, date, method, ref);
    if (created) {
      setPayOpen(false);
      router.push(`/app/doc/${created.id}`);
    }
  }

  return (
    <div className="no-print flex flex-wrap items-center gap-2">
      <Button variant="outline" onClick={printPage}>
        Print / save PDF
      </Button>
      {doc.book === "quotation" && doc.status !== "void" && !converted && (
        <Button onClick={onConvert}>Convert to invoice</Button>
      )}
      {converted && (
        <Button variant="outline" render={<Link href={`/app/doc/${converted.id}`} />}>
          Open {converted.number}
        </Button>
      )}
      {(doc.book === "invoice" || doc.book === "bill") &&
        doc.status !== "void" &&
        due > 0 && (
          <Button onClick={() => setPayOpen(true)}>
            {doc.book === "invoice" ? "Issue receipt" : "Issue voucher"} · {ugx(due)} due
          </Button>
        )}
      {doc.status !== "void" && (
        <Button variant="destructive" onClick={() => setVoidOpen(true)}>
          Void this page
        </Button>
      )}
      <Button
        variant="ghost"
        render={<Link href={`/app/books/${BOOK_META[doc.book].slug}`} />}
      >
        Back to {BOOK_META[doc.book].short.toLowerCase()}
      </Button>

      <Dialog open={voidOpen} onOpenChange={setVoidOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>Void {doc.number}</DialogTitle>
            <DialogDescription>
              The number stays in the book, ruled off like a spoiled page. You cannot
              reuse it. This is how indigenous books stay auditable.
            </DialogDescription>
          </DialogHeader>
          <div>
            <Label htmlFor="reason">Reason</Label>
            <Input
              id="reason"
              className="mt-1"
              value={reason}
              onChange={(e) => setReason(e.target.value)}
              placeholder="Spoiled, wrong customer, duplicate…"
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setVoidOpen(false)}>
              Keep
            </Button>
            <Button variant="destructive" onClick={onVoid} disabled={!reason.trim()}>
              Void page
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      <Dialog open={payOpen} onOpenChange={setPayOpen}>
        <DialogContent className="sm:max-w-md">
          <DialogHeader>
            <DialogTitle>
              {doc.book === "invoice" ? "Official receipt" : "Payment voucher"} against{" "}
              {doc.number}
            </DialogTitle>
            <DialogDescription>
              Outstanding {ugx(due)}. Status {status}. The next sequential number will be
              locked when you issue.
            </DialogDescription>
          </DialogHeader>
          <div className="grid gap-3">
            <div>
              <Label htmlFor="pay-date">Date</Label>
              <Input
                id="pay-date"
                type="date"
                className="mt-1"
                value={date}
                onChange={(e) => setDate(e.target.value)}
              />
            </div>
            <div>
              <Label htmlFor="pay-amount">Amount (UGX)</Label>
              <Input
                id="pay-amount"
                className="mt-1 font-mono"
                value={amount}
                onChange={(e) => setAmount(e.target.value)}
              />
            </div>
            <div>
              <Label htmlFor="pay-method">How paid</Label>
              <select
                id="pay-method"
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
            <div>
              <Label htmlFor="pay-ref">Reference</Label>
              <Input
                id="pay-ref"
                className="mt-1"
                value={ref}
                onChange={(e) => setRef(e.target.value)}
                placeholder="Cheque no. / MoMo / EFT"
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPayOpen(false)}>
              Cancel
            </Button>
            <Button onClick={onPay}>Issue</Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
}
