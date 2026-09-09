"use client";

import { Button } from "@/components/ui/button";
import { PLANS } from "@/lib/plans";
import { ugx } from "@/lib/money";
import { useBooks } from "@/lib/store";
import type { PlanId } from "@/lib/types";

export default function SubscribePage() {
  const { state, saveBranding } = useBooks();

  function choose(plan: PlanId) {
    saveBranding({ ...state.branding, plan });
  }

  return (
    <div className="mx-auto max-w-5xl">
      <h1 className="text-2xl font-semibold tracking-tight">Annual plan</h1>
      <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
        One MoMo payment a year. This demo does not take money — pick a plan to see whether
        EFRIS marks appear on new invoices.
      </p>
      <div className="mt-8 grid gap-4 lg:grid-cols-3">
        {(Object.keys(PLANS) as PlanId[]).map((id) => {
          const plan = PLANS[id];
          const current = state.branding.plan === id;
          return (
            <article
              key={id}
              className={`flex flex-col rounded-lg border bg-card p-5 ${
                current ? "border-primary ring-1 ring-primary/30" : ""
              }`}
            >
              <p className="text-lg font-semibold">{plan.name}</p>
              <p className="mt-1 font-mono text-2xl">{ugx(plan.price)}</p>
              <p className="text-xs text-muted-foreground">a year</p>
              <p className="mt-3 text-sm text-muted-foreground">{plan.blurb}</p>
              <ul className="mt-4 flex-1 space-y-1.5 text-sm">
                {plan.points.map((p) => (
                  <li key={p}>· {p}</li>
                ))}
              </ul>
              <Button className="mt-6" variant={current ? "default" : "outline"} onClick={() => choose(id)}>
                {current ? "Current plan" : `Use ${plan.name}`}
              </Button>
            </article>
          );
        })}
      </div>
    </div>
  );
}
