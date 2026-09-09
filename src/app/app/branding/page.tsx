"use client";

import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { BrandedSheet } from "@/components/branded-sheet";
import { useBooks } from "@/lib/store";
import type { Branding } from "@/lib/types";

export default function BrandingPage() {
  const { state, saveBranding } = useBooks();
  const [draft, setDraft] = useState<Branding>(state.branding);
  const sample = state.documents.find((d) => d.id === "inv-sample") ?? state.documents.find((d) => d.kind === "invoice");
  const party = state.parties.find((p) => p.id === sample?.partyId);

  function patch(p: Partial<Branding>) {
    setDraft((d) => ({ ...d, ...p }));
  }

  function onLogo(file: File | undefined) {
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => patch({ logoDataUrl: String(reader.result) });
    reader.readAsDataURL(file);
  }

  return (
    <div className="mx-auto grid max-w-6xl gap-8 lg:grid-cols-2">
      <div>
        <h1 className="text-2xl font-semibold tracking-tight">Branding</h1>
        <p className="mt-1 text-sm text-muted-foreground">
          Set once. Every invoice, receipt, quotation and letter uses this header and colour —
          the same idea as the Ofagros page you uploaded.
        </p>
        <div className="mt-6 space-y-3">
          <Field label="Company name">
            <Input value={draft.name} onChange={(e) => patch({ name: e.target.value })} />
          </Field>
          <Field label="Tagline">
            <Input value={draft.tagline} onChange={(e) => patch({ tagline: e.target.value })} />
          </Field>
          <Field label="Logo">
            <Input type="file" accept="image/*" onChange={(e) => onLogo(e.target.files?.[0])} />
          </Field>
          <Field label="Brand colour">
            <div className="flex items-center gap-2">
              <input
                type="color"
                value={draft.brandColor}
                onChange={(e) => patch({ brandColor: e.target.value })}
                className="h-8 w-12 cursor-pointer rounded border"
              />
              <Input value={draft.brandColor} onChange={(e) => patch({ brandColor: e.target.value })} />
            </div>
          </Field>
          <Field label="TIN">
            <Input value={draft.tin} onChange={(e) => patch({ tin: e.target.value })} />
          </Field>
          <Field label="Address">
            <Input value={draft.address} onChange={(e) => patch({ address: e.target.value })} />
          </Field>
          <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Phone">
              <Input value={draft.phone} onChange={(e) => patch({ phone: e.target.value })} />
            </Field>
            <Field label="Email">
              <Input value={draft.email} onChange={(e) => patch({ email: e.target.value })} />
            </Field>
          </div>
          <Field label="Website">
            <Input value={draft.website} onChange={(e) => patch({ website: e.target.value })} />
          </Field>
          <Field label="Document prefix">
            <Input value={draft.prefix} onChange={(e) => patch({ prefix: e.target.value })} />
          </Field>
          <Field label="Payment note">
            <Input value={draft.paymentNote} onChange={(e) => patch({ paymentNote: e.target.value })} />
          </Field>
          <Field label="Default invoice comments">
            <Textarea rows={4} value={draft.invoiceComments} onChange={(e) => patch({ invoiceComments: e.target.value })} />
          </Field>
          <Button onClick={() => saveBranding(draft)}>Save branding</Button>
        </div>
      </div>
      {sample && (
        <div className="overflow-auto rounded-lg border bg-neutral-200 p-3">
          <div className="origin-top scale-[0.72]">
            <BrandedSheet branding={draft} doc={sample} party={party} />
          </div>
        </div>
      )}
    </div>
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
