"use client";

import { useBooks } from "@/lib/store";

export default function ClientsPage() {
  const { state } = useBooks();
  return (
    <div className="mx-auto max-w-3xl">
      <h1 className="text-2xl font-semibold tracking-tight">Clients &amp; suppliers</h1>
      <p className="mt-1 text-sm text-muted-foreground">
        Names as they appear on the PDF. Add a new one when you issue a document.
      </p>
      <div className="mt-6 divide-y rounded-lg border bg-card">
        {state.parties.map((p) => (
          <div key={p.id} className="px-4 py-3">
            <p className="font-medium">{p.name}</p>
            <p className="text-xs text-muted-foreground">
              {p.kind} {p.tin ? `· TIN ${p.tin}` : ""} {p.email ? `· ${p.email}` : ""}{" "}
              {p.phone ? `· ${p.phone}` : ""}
            </p>
            {p.address && <p className="text-xs text-muted-foreground">{p.address}</p>}
          </div>
        ))}
      </div>
    </div>
  );
}
