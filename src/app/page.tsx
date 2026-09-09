"use client";

import Link from "next/link";
import { BrandedSheet } from "@/components/branded-sheet";
import { Button } from "@/components/ui/button";
import { PLANS } from "@/lib/plans";
import { ugx } from "@/lib/money";
import { partyById } from "@/lib/reports";
import { useBooks } from "@/lib/store";
import type { PlanId } from "@/lib/types";

export default function LandingPage() {
  const { state } = useBooks();
  const sample =
    state.documents.find((d) => d.id === "inv-sample") ??
    state.documents.find((d) => d.kind === "invoice");
  const party = sample ? partyById(state, sample.partyId) : undefined;

  return (
    <div className="min-h-svh bg-white">
      <header className="mx-auto flex max-w-6xl items-center justify-between px-4 py-4">
        <Link href="/" className="text-sm font-semibold tracking-[0.2em] uppercase">
          Folio
        </Link>
        <div className="flex gap-2">
          <Button variant="ghost" render={<Link href="/app/subscribe" />}>
            Pricing
          </Button>
          <Button render={<Link href="/app" />}>Open the desk</Button>
        </div>
      </header>

      <section className="mx-auto grid max-w-6xl items-start gap-10 px-4 py-10 lg:grid-cols-2 lg:py-16">
        <div>
          <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
            Annual books for Ugandan SMEs
          </p>
          <h1 className="mt-3 text-4xl font-semibold tracking-tight sm:text-5xl">
            Invoices that look like your company, not like software.
          </h1>
          <p className="mt-4 max-w-xl text-muted-foreground">
            Upload your logo and colour once. Issue a quotation, invoice, receipt or headed
            letter. Download the PDF or email it. SME plan adds VAT and an EFRIS mark. No stock
            module. No extra pictures. UGX 150,000–350,000 a year.
          </p>
          <div className="mt-6 flex flex-wrap gap-3">
            <Button size="lg" render={<Link href="/app/doc/inv-sample" />}>
              See the Ofagros invoice
            </Button>
            <Button size="lg" variant="outline" render={<Link href="/app/invoices/new" />}>
              Issue one in two minutes
            </Button>
          </div>
        </div>
        <div className="overflow-hidden rounded-lg border bg-neutral-100 p-3">
          {sample && (
            <div className="origin-top scale-[0.62] sm:scale-[0.72]">
              <BrandedSheet branding={state.branding} doc={sample} party={party} />
            </div>
          )}
        </div>
      </section>

      <section className="border-y bg-muted/40">
        <div className="mx-auto grid max-w-6xl gap-4 px-4 py-12 sm:grid-cols-3">
          {(Object.keys(PLANS) as PlanId[]).map((id) => {
            const plan = PLANS[id];
            return (
              <article key={id} className="rounded-lg border bg-card p-5">
                <p className="font-semibold">{plan.name}</p>
                <p className="mt-1 font-mono text-2xl">{ugx(plan.price)}</p>
                <p className="text-xs text-muted-foreground">a year, one payment</p>
                <p className="mt-3 text-sm text-muted-foreground">{plan.blurb}</p>
              </article>
            );
          })}
        </div>
      </section>

      <footer className="px-4 py-8 text-center text-xs text-muted-foreground">
        Folio is a working slice. EFRIS marks in this demo are not live URA fiscalisation.
      </footer>
    </div>
  );
}
