"use client";

import { BooksProvider } from "@/lib/store";

export function Providers({ children }: { children: React.ReactNode }) {
  return <BooksProvider>{children}</BooksProvider>;
}
