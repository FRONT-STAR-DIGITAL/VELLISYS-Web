"use client";

import Link from "next/link";
import { Stationery } from "@/components/stationery";
import { Button } from "@/components/ui/button";
import { BOOK_META, BOOK_ORDER } from "@/lib/books";
import { partyById } from "@/lib/ledgers";
import { useBooks } from "@/lib/store";

const CATCH = [
  {
    kicker: "The form they already know",
    title: "Do not teach the cashier QuickBooks.",
    body: "Quotations, tax invoices, official receipts, supplier bills, payment vouchers. Sequential numbers in red. Amount in words. TIN. VAT 18%. Original, duplicate, counterfoil. The page looks like the pad from the stationer on Luwum Street — because that is the product, not a Western ledger wearing a login screen.",
  },
  {
    kicker: "Numbers you cannot cheat",
    title: "A spoiled page is voided, never deleted.",
    body: "Indigenous books work because you cannot tear out a number and pretend it was never written. Counterfoil locks INV-2026-0047 the moment it is issued. Void it and the cancelled stamp stays in the book. Auditors, URA inspectors, and the director who signs cheques all understand that.",
  },
  {
    kicker: "The ledger nobody kept",
    title: "Fill the same forms. Debtors and creditors write themselves.",
    body: "The paper receipt book never told you who still owes sixty days out. That was a second exercise the clerk started in a counter book and abandoned in March. Here, an invoice opens a debtor line; a receipt knocks it down; aging is current / 30 / 60 / 90. Same for suppliers. No double entry class required.",
  },
  {
    kicker: "The annual purchase",
    title: "You already bought the year in books.",
    body: "Companies do not want a monthly SaaS surprise. They buy stationery once, for the year. Counterfoil is that purchase: an annual subscription that is the 2026 books — unlimited pages, one number series, the counterfoil that cannot go missing in a cardboard box under the cashier’s desk.",
  },
];

export default function LandingPage() {
  const { state } = useBooks();
  const sample =
    state.documents.find((d) => d.id === "inv-6") ??
    state.documents.find((d) => d.book === "invoice");
  const party = sample ? partyById(state, sample.partyId) : undefined;

  return (
    <div className="flex min-h-svh flex-col">
      <header className="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4 sm:px-6">
        <Link href="/" className="flex items-baseline gap-2">
          <span className="font-heading text-xl">Counterfoil</span>
          <span className="hidden text-[11px] uppercase tracking-[0.2em] text-stamp sm:inline">
            Kampala
          </span>
        </Link>
        <div className="flex items-center gap-2">
          <Button variant="ghost" render={<Link href="/app/subscribe" />}>
            Annual books
          </Button>
          <Button render={<Link href="/app" />}>Open the 2026 books</Button>
        </div>
      </header>

      <main className="flex-1">
        <section className="mx-auto grid w-full max-w-6xl items-center gap-10 px-4 py-10 sm:px-6 lg:grid-cols-2 lg:py-16">
          <div>
            <p className="text-[11px] uppercase tracking-[0.28em] text-stamp">
              The receipt book that keeps your books
            </p>
            <h1 className="mt-3 font-heading text-4xl leading-[1.1] sm:text-5xl">
              Your catch is not another accounts package.
              <span className="italic text-primary"> It is the carbon copy.</span>
            </h1>
            <p className="mt-5 max-w-xl text-base leading-relaxed text-muted-foreground sm:text-lg">
              Ugandan corporates already know how money is written down: a numbered
              pad, a customer copy, an office stub. Western software starts from
              journals. Counterfoil starts from the page the cashier already fills —
              then quietly keeps debtors, creditors, and the year.
            </p>
            <div className="mt-6 flex flex-wrap gap-3">
              <Button size="lg" render={<Link href="/app" />}>
                Sit at the demo desk
              </Button>
              <Button size="lg" variant="outline" render={<Link href="/app/debtors" />}>
                See the debtors book
              </Button>
            </div>
            <p className="mt-4 text-sm text-muted-foreground">
              Pearl Traders Ltd · Industrial Area, Kampala · books of 2026, already written.
            </p>
          </div>

          <div className="relative">
            <div className="absolute -inset-3 -z-10 rotate-2 rounded-md bg-[var(--carbon-yellow)] shadow-sm" />
            <div className="absolute -inset-1 -z-10 -rotate-1 rounded-md bg-[var(--carbon-pink)]" />
            {sample && <Stationery doc={sample} party={party} copy="original" compact />}
          </div>
        </section>

        <section className="border-y border-border bg-card/60">
          <div className="mx-auto grid max-w-6xl gap-8 px-4 py-14 sm:px-6 md:grid-cols-2">
            {CATCH.map((item) => (
              <article key={item.title}>
                <p className="text-[11px] uppercase tracking-[0.2em] text-stamp">
                  {item.kicker}
                </p>
                <h2 className="mt-2 font-heading text-2xl">{item.title}</h2>
                <p className="mt-2 text-sm leading-relaxed text-muted-foreground sm:text-[15px]">
                  {item.body}
                </p>
              </article>
            ))}
          </div>
        </section>

        <section className="mx-auto max-w-6xl px-4 py-14 sm:px-6">
          <h2 className="font-heading text-3xl">The five books on the desk</h2>
          <p className="mt-2 max-w-2xl text-muted-foreground">
            Not modules. Books. The same set a Kampala accounts clerk lays out on
            Monday morning — plus the two ledgers that used to live in a separate
            counter book.
          </p>
          <ul className="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            {BOOK_ORDER.map((book) => {
              const meta = BOOK_META[book];
              return (
                <li
                  key={book}
                  className="rounded-lg border border-border bg-card p-4 shadow-sm"
                >
                  <p className="font-mono text-xs text-stamp">{meta.prefix}</p>
                  <p className="mt-2 font-heading text-lg leading-tight">{meta.short}</p>
                  <p className="mt-2 text-xs text-muted-foreground">{meta.blurb}</p>
                </li>
              );
            })}
          </ul>
        </section>

        <section className="border-t border-border bg-primary text-primary-foreground">
          <div className="mx-auto flex max-w-6xl flex-col items-start justify-between gap-6 px-4 py-12 sm:flex-row sm:items-center sm:px-6">
            <div>
              <h2 className="font-heading text-3xl">Open the 2026 books</h2>
              <p className="mt-2 max-w-xl text-sm text-primary-foreground/75">
                Pearl Traders is a seeded Kampala wholesaler — invoices to Speke, KCCA,
                UNRA; bills from Roofings and Hima; receipts that have already knocked
                some balances down. Issue the next page. Watch the debtor move.
              </p>
            </div>
            <Button
              size="lg"
              variant="secondary"
              render={<Link href="/app" />}
            >
              Enter the desk
            </Button>
          </div>
        </section>
      </main>

      <footer className="border-t border-border px-4 py-6 text-center text-xs text-muted-foreground">
        Counterfoil is a working slice of an annual subscription for Ugandan corporate
        books. It is not a URA eFRIS fiscaliser and does not replace a licensed
        accountant.
      </footer>
    </div>
  );
}
