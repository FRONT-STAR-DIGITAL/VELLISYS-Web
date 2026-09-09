"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import {
  BookOpen,
  BookText,
  CircleDollarSign,
  FileSpreadsheet,
  Menu,
  Receipt,
  ScrollText,
  Users,
  WalletCards,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTrigger, SheetTitle } from "@/components/ui/sheet";
import { BOOK_META } from "@/lib/books";
import { useBooks } from "@/lib/store";
import { cn } from "@/lib/utils";

const NAV = [
  { href: "/app", label: "The desk", icon: BookOpen, exact: true },
  { href: "/app/books/quotations", label: BOOK_META.quotation.short, icon: ScrollText },
  { href: "/app/books/invoices", label: BOOK_META.invoice.short, icon: FileSpreadsheet },
  { href: "/app/books/receipts", label: BOOK_META.receipt.short, icon: Receipt },
  { href: "/app/books/bills", label: BOOK_META.bill.short, icon: BookText },
  { href: "/app/books/vouchers", label: BOOK_META.voucher.short, icon: WalletCards },
  { href: "/app/debtors", label: "Debtors", icon: Users },
  { href: "/app/creditors", label: "Creditors", icon: CircleDollarSign },
];

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
  const pathname = usePathname();
  return (
    <nav className="flex flex-col gap-0.5">
      {NAV.map((item) => {
        const active = item.exact
          ? pathname === item.href
          : pathname === item.href || pathname.startsWith(item.href + "/");
        const Icon = item.icon;
        return (
          <Link
            key={item.href}
            href={item.href}
            onClick={onNavigate}
            className={cn(
              "flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm",
              active
                ? "bg-sidebar-accent text-sidebar-accent-foreground"
                : "text-sidebar-foreground/75 hover:bg-sidebar-accent/60 hover:text-sidebar-foreground",
            )}
          >
            <Icon className="size-4 shrink-0" />
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
}

export function AppShell({ children }: { children: React.ReactNode }) {
  const { state } = useBooks();
  const [open, setOpen] = useState(false);

  return (
    <div className="flex min-h-svh">
      <aside className="no-print hidden w-60 shrink-0 flex-col bg-sidebar text-sidebar-foreground md:flex">
        <div className="px-4 pb-2 pt-5">
          <Link href="/" className="block">
            <p className="font-heading text-[11px] tracking-[0.28em] text-sidebar-primary uppercase">
              Counterfoil
            </p>
            <p className="mt-1 font-heading text-lg leading-tight">
              {state.company.tradingAs}
            </p>
          </Link>
          <p className="mt-1 text-[11px] text-sidebar-foreground/55">
            Books of {state.company.booksYear} · TIN {state.company.tin}
          </p>
        </div>
        <div className="flex-1 px-2 py-3">
          <NavLinks />
        </div>
        <div className="border-t border-sidebar-border px-4 py-3">
          <Link
            href="/app/subscribe"
            className="text-xs text-sidebar-foreground/70 hover:text-sidebar-primary"
          >
            2026 subscription · Corporate
          </Link>
        </div>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="no-print flex items-center gap-3 border-b border-border bg-card/80 px-3 py-2.5 backdrop-blur md:hidden">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger render={<Button variant="outline" size="icon-sm" />}>
              <Menu className="size-4" />
            </SheetTrigger>
            <SheetContent side="left" className="w-64 bg-sidebar p-0 text-sidebar-foreground">
              <SheetTitle className="sr-only">Books</SheetTitle>
              <div className="px-4 pb-2 pt-8">
                <p className="font-heading tracking-[0.28em] text-sidebar-primary uppercase text-[11px]">
                  Counterfoil
                </p>
              </div>
              <div className="px-2 pb-6">
                <NavLinks onNavigate={() => setOpen(false)} />
              </div>
            </SheetContent>
          </Sheet>
          <div className="min-w-0">
            <p className="truncate font-heading text-base">{state.company.tradingAs}</p>
            <p className="text-[11px] text-muted-foreground">Books of {state.company.booksYear}</p>
          </div>
        </header>
        <main className="flex-1 px-4 py-6 sm:px-8 sm:py-8">{children}</main>
      </div>
    </div>
  );
}
