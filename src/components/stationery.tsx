"use client";

import { useState } from "react";
import type { CopyKind, DocumentRecord, Party } from "@/lib/types";
import { BOOK_META, COPY_META, PAYMENT_METHODS } from "@/lib/books";
import {
  amountInWords,
  formatDate,
  grandTotal,
  lineAmount,
  subtotal,
  ugx,
  vatAmount,
} from "@/lib/money";
import { cn } from "@/lib/utils";
import { useBooks } from "@/lib/store";

const copyTone: Record<CopyKind, string> = {
  original: "bg-[var(--paper)]",
  duplicate: "bg-[var(--carbon-yellow)]",
  counterfoil: "bg-[var(--carbon-pink)]",
};

export function Stationery({
  doc,
  party,
  copy = "original",
  compact = false,
}: {
  doc: DocumentRecord;
  party?: Party;
  copy?: CopyKind;
  compact?: boolean;
}) {
  const { state } = useBooks();
  const company = state.company;
  const meta = BOOK_META[doc.book];
  const total = grandTotal(doc.items, doc.vatRate);
  const net = subtotal(doc.items);
  const vat = vatAmount(doc.items, doc.vatRate);
  const method = PAYMENT_METHODS.find((m) => m.value === doc.paymentMethod)?.label;
  const related = doc.relatedDocumentId
    ? state.documents.find((d) => d.id === doc.relatedDocumentId)
    : undefined;

  return (
    <article
      className={cn(
        "paper-grain relative overflow-hidden rounded-sm border border-ink/20 text-ink shadow-[0_12px_40px_-18px_rgba(40,24,8,0.45)]",
        copyTone[copy],
        compact ? "p-5 text-[12px]" : "p-6 sm:p-8 text-[13px]",
        doc.status === "void" && "opacity-80",
      )}
    >
      <div
        className="pointer-events-none absolute inset-y-0 left-0 w-2"
        style={{
          backgroundImage:
            "repeating-linear-gradient(to bottom, #1a1a1a 0 10px, #f0c53a 10px 20px, #c81e1e 20px 30px)",
        }}
      />

      {doc.status === "void" && (
        <div className="pointer-events-none absolute inset-0 z-10 flex items-center justify-center">
          <span className="stamp text-3xl sm:text-5xl opacity-70">Cancelled</span>
        </div>
      )}

      <header className="relative flex items-start justify-between gap-4 border-b border-stamp/30 pb-4 pl-3">
        <div>
          <p className="font-heading text-[11px] tracking-[0.28em] text-stamp uppercase">
            {company.tradingAs ?? company.name}
          </p>
          <h2 className="font-heading text-xl sm:text-2xl leading-tight text-ink">
            {company.name}
          </h2>
          <p className="mt-1 max-w-sm text-[11px] leading-relaxed text-ink/70">
            {company.address}
            <br />
            {company.city}
            <br />
            TIN {company.tin} · VAT {company.vatNo}
            <br />
            {company.phone} · {company.email}
          </p>
        </div>
        <div className="text-right">
          <p className="font-heading text-[10px] tracking-[0.2em] text-ink/50">
            No.
          </p>
          <p className="font-mono text-lg sm:text-xl font-semibold text-stamp">
            {doc.number}
          </p>
          <div className="mt-3 flex justify-end">
            <span className="stamp text-[10px] sm:text-xs">{COPY_META[copy].label}</span>
          </div>
        </div>
      </header>

      <div className="relative mt-5 pl-3">
        <p className="text-center font-heading text-lg sm:text-xl tracking-[0.22em] text-ink">
          {meta.stationeryTitle}
        </p>
        <div className="mt-4 grid gap-3 sm:grid-cols-2">
          <div>
            <p className="text-[10px] uppercase tracking-[0.16em] text-ink/45">
              {meta.partyRole === "customer" ? "To / Received from" : "Payee / Supplier"}
            </p>
            <p className="font-medium">{party?.name ?? "—"}</p>
            {party?.address && (
              <p className="text-[11px] text-ink/70">{party.address}</p>
            )}
            {party?.tin && (
              <p className="text-[11px] text-ink/70">TIN {party.tin}</p>
            )}
          </div>
          <div className="sm:text-right">
            <p className="text-[10px] uppercase tracking-[0.16em] text-ink/45">Date</p>
            <p className="font-medium">{formatDate(doc.date)}</p>
            {related && (
              <p className="mt-1 text-[11px] text-ink/70">Ref. {related.number}</p>
            )}
            {method && (
              <p className="text-[11px] text-ink/70">
                {method}
                {doc.paymentRef ? ` · ${doc.paymentRef}` : ""}
              </p>
            )}
          </div>
        </div>
      </div>

      <div className="relative mt-5 overflow-x-auto pl-3">
        <table className="w-full border-collapse text-left">
          <thead>
            <tr className="border-y border-ink/25 text-[10px] uppercase tracking-[0.14em] text-ink/55">
              <th className="py-2 pr-2 font-medium">Particulars</th>
              <th className="py-2 px-2 font-medium text-right">Qty</th>
              <th className="py-2 px-2 font-medium">Unit</th>
              <th className="py-2 px-2 font-medium text-right">Rate</th>
              <th className="py-2 pl-2 font-medium text-right">Amount</th>
            </tr>
          </thead>
          <tbody>
            {doc.items.map((item, i) => (
              <tr key={i} className="border-b border-ink/10">
                <td className="py-2 pr-2">{item.description}</td>
                <td className="py-2 px-2 text-right font-mono tabular-nums">
                  {item.qty.toLocaleString("en-UG")}
                </td>
                <td className="py-2 px-2 text-ink/70">{item.unit}</td>
                <td className="py-2 px-2 text-right font-mono tabular-nums">
                  {ugx(item.rate)}
                </td>
                <td className="py-2 pl-2 text-right font-mono tabular-nums">
                  {ugx(lineAmount(item))}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="relative mt-4 flex flex-col gap-3 pl-3 sm:flex-row sm:items-end sm:justify-between">
        <p className="max-w-md text-[12px] italic leading-relaxed text-ink/80">
          {amountInWords(total)}
        </p>
        <dl className="min-w-[220px] space-y-1 text-sm">
          {doc.vatRate > 0 && (
            <>
              <div className="flex justify-between gap-6">
                <dt className="text-ink/60">Subtotal</dt>
                <dd className="font-mono tabular-nums">{ugx(net)}</dd>
              </div>
              <div className="flex justify-between gap-6">
                <dt className="text-ink/60">VAT {Math.round(doc.vatRate * 100)}%</dt>
                <dd className="font-mono tabular-nums">{ugx(vat)}</dd>
              </div>
            </>
          )}
          <div className="flex justify-between gap-6 border-t border-ink/20 pt-1 font-semibold">
            <dt>Total</dt>
            <dd className="font-mono tabular-nums text-stamp">{ugx(total)}</dd>
          </div>
        </dl>
      </div>

      {doc.notes && (
        <p className="relative mt-4 pl-3 text-[12px] text-ink/70">
          <span className="uppercase tracking-[0.14em] text-ink/45">Remarks. </span>
          {doc.notes}
        </p>
      )}

      {doc.status === "void" && doc.voidReason && (
        <p className="relative mt-2 pl-3 text-[12px] text-stamp">
          Voided: {doc.voidReason}
        </p>
      )}

      <footer className="relative mt-8 grid gap-6 pl-3 sm:grid-cols-2">
        <div>
          <div className="h-10 border-b border-dotted border-ink/40" />
          <p className="mt-1 text-[10px] uppercase tracking-[0.16em] text-ink/50">
            Prepared / cashier
          </p>
        </div>
        <div>
          <div className="h-10 border-b border-dotted border-ink/40" />
          <p className="mt-1 text-[10px] uppercase tracking-[0.16em] text-ink/50">
            Authorised signature &amp; stamp
          </p>
        </div>
      </footer>

      <p className="relative mt-6 pl-3 text-center text-[10px] tracking-[0.12em] text-ink/40">
        {COPY_META[copy].hint} · Books of {company.booksYear} · Sequential number cannot be reused
      </p>
    </article>
  );
}

export function StationeryDeck({
  doc,
  party,
}: {
  doc: DocumentRecord;
  party?: Party;
}) {
  const [copy, setCopy] = useState<CopyKind>("original");

  return (
    <div>
      <div className="no-print mb-4 flex flex-wrap gap-1 rounded-lg border border-border bg-card p-1">
        {(["original", "duplicate", "counterfoil"] as CopyKind[]).map((kind) => (
          <button
            key={kind}
            type="button"
            onClick={() => setCopy(kind)}
            className={cn(
              "flex-1 rounded-md px-3 py-1.5 text-xs font-medium tracking-wide uppercase",
              copy === kind
                ? "bg-primary text-primary-foreground"
                : "text-muted-foreground hover:text-foreground",
            )}
          >
            {COPY_META[kind].label}
          </button>
        ))}
      </div>
      <Stationery doc={doc} party={party} copy={copy} />
    </div>
  );
}
