import { Badge } from "@/components/ui/badge";
import { cn } from "@/lib/utils";

const TONES: Record<string, string> = {
  paid: "bg-primary/10 text-primary border-transparent",
  open: "bg-secondary text-secondary-foreground",
  "part-paid": "bg-amber-100 text-amber-900 border-transparent",
  overdue: "bg-destructive/10 text-destructive border-transparent",
  void: "bg-muted text-muted-foreground line-through",
  issued: "bg-secondary text-secondary-foreground",
  invoiced: "bg-primary/10 text-primary border-transparent",
};

const LABELS: Record<string, string> = {
  paid: "Paid",
  open: "Open",
  "part-paid": "Part paid",
  overdue: "Overdue",
  void: "Void",
  issued: "Issued",
  invoiced: "Invoiced",
};

export function StatusChip({ status }: { status: string }) {
  return (
    <Badge
      variant="outline"
      className={cn("capitalize", TONES[status] ?? TONES.issued)}
    >
      {LABELS[status] ?? status}
    </Badge>
  );
}
