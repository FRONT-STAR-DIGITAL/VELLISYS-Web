"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { useState } from "react";
import {
  BookOpen,
  FileText,
  Files,
  Mail,
  Menu,
  Palette,
  PieChart,
  Receipt,
  Settings,
  Users,
  Wallet,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { Sheet, SheetContent, SheetTitle, SheetTrigger } from "@/components/ui/sheet";
import { useBooks } from "@/lib/store";
import { cn } from "@/lib/utils";

const NAV = [
  { href: "/app", label: "Desk", icon: BookOpen, exact: true },
  { href: "/app/invoices", label: "Invoices", icon: Files },
  { href: "/app/quotations", label: "Quotations", icon: FileText },
  { href: "/app/receipts", label: "Receipts", icon: Receipt },
  { href: "/app/expenses", label: "Expenses", icon: Wallet },
  { href: "/app/letters", label: "Letters", icon: Mail },
  { href: "/app/clients", label: "Clients", icon: Users },
  { href: "/app/reports", label: "Reports", icon: PieChart },
  { href: "/app/branding", label: "Branding", icon: Palette },
  { href: "/app/subscribe", label: "Plan", icon: Settings },
];

function Links({ onGo }: { onGo?: () => void }) {
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
            onClick={onGo}
            className={cn(
              "flex items-center gap-2.5 rounded-md px-2.5 py-2 text-sm",
              active
                ? "bg-primary text-primary-foreground"
                : "text-foreground/80 hover:bg-muted",
            )}
          >
            <Icon className="size-4" />
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
    <div className="flex min-h-svh bg-muted/40">
      <aside className="no-print hidden w-56 shrink-0 border-r border-border bg-card md:flex md:flex-col">
        <div className="px-4 py-4">
          <Link href="/" className="block">
            <p className="text-[11px] font-semibold uppercase tracking-[0.2em] text-muted-foreground">
              Folio
            </p>
            <p className="mt-1 truncate text-sm font-semibold">{state.branding.name}</p>
          </Link>
        </div>
        <div className="flex-1 px-2">
          <Links />
        </div>
        <p className="px-4 py-3 text-[11px] text-muted-foreground">
          {state.branding.plan.toUpperCase()} · UGX{" "}
          {state.branding.plan === "starter"
            ? "150,000"
            : state.branding.plan === "sme"
              ? "250,000"
              : "350,000"}
          /year
        </p>
      </aside>
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="no-print flex items-center gap-3 border-b border-border bg-card px-3 py-2 md:hidden">
          <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger render={<Button variant="outline" size="icon-sm" />}>
              <Menu />
            </SheetTrigger>
            <SheetContent side="left" className="w-64 bg-card p-4">
              <SheetTitle>Folio</SheetTitle>
              <div className="mt-4">
                <Links onGo={() => setOpen(false)} />
              </div>
            </SheetContent>
          </Sheet>
          <p className="truncate font-medium">{state.branding.name}</p>
        </header>
        <main className="flex-1 px-4 py-6 sm:px-8 print:p-0">{children}</main>
      </div>
    </div>
  );
}
