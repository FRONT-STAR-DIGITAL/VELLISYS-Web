"use client";

import type { Branding, DocumentRecord, Party } from "@/lib/types";
import {
  formatDate,
  grandTotal,
  hexTint,
  lineAmount,
  subtotal,
  ugx,
  vatAmount,
} from "@/lib/money";
import { KIND_META } from "@/lib/plans";
import { QrMark } from "@/components/qr-mark";

const EMPTY_ROWS = 6;

export function BrandedSheet({
  branding,
  doc,
  party,
}: {
  branding: Branding;
  doc: DocumentRecord;
  party?: Party;
}) {
  const color = branding.brandColor || "#82B440";
  const stripe = hexTint(color, 0.92);
  const heading = KIND_META[doc.kind].heading;
  const net = subtotal(doc.items);
  const vat = vatAmount(doc.items, doc.vatRate);
  const total = grandTotal(doc.items, doc.vatRate);
  const comments = (doc.notes || branding.invoiceComments || "").trim();
  const rows =
    doc.kind === "letter"
      ? []
      : [
          ...doc.items,
          ...Array.from({ length: Math.max(0, EMPTY_ROWS - doc.items.length) }, () => null),
        ];

  const meta: [string, string][] = [
    ["DATE", formatDate(doc.date)],
    [
      doc.kind === "quotation"
        ? "QUOTE #"
        : doc.kind === "receipt"
          ? "RECEIPT #"
          : doc.kind === "expense"
            ? "EXPENSE #"
            : doc.kind === "letter"
              ? "REF"
              : "INVOICE #",
      doc.number,
    ],
  ];
  if (party) meta.push(["CUSTOMER ID", party.id.replace("pty-", "").toUpperCase()]);
  if (doc.dueDate) {
    meta.push([doc.kind === "quotation" ? "VALID TO" : "DUE DATE", formatDate(doc.dueDate)]);
  }

  return (
    <article
      className="invoice-sheet mx-auto bg-white text-black"
      style={{
        width: "210mm",
        minHeight: "297mm",
        padding: "14mm 16mm 16mm",
        fontFamily: "Arial, Helvetica, sans-serif",
        fontSize: 12.5,
        color: "#222",
        boxSizing: "border-box",
      }}
    >
      <header className="flex items-start justify-between gap-6">
        <div className="min-w-0">
          {branding.logoDataUrl ? (
            // eslint-disable-next-line @next/next/no-img-element
            <img
              src={branding.logoDataUrl}
              alt={branding.name}
              style={{ height: 52, width: "auto", maxWidth: 260, objectFit: "contain" }}
            />
          ) : (
            <div>
              <p style={{ fontSize: 28, fontWeight: 800, color, margin: 0 }}>{branding.name}</p>
              {branding.tagline && (
                <p style={{ margin: 0, fontStyle: "italic", fontSize: 11 }}>{branding.tagline}</p>
              )}
            </div>
          )}
          <div style={{ marginTop: 10, fontSize: 11, lineHeight: 1.45, color: "#333" }}>
            <div>{branding.address}</div>
            <div>{branding.phone}</div>
            <div>{branding.email}</div>
            <div>{branding.website}</div>
            {branding.tin && <div>TIN {branding.tin}</div>}
          </div>
        </div>
        <div style={{ minWidth: 220 }}>
          <p
            style={{
              margin: 0,
              textAlign: "right",
              fontSize: 36,
              fontWeight: 700,
              letterSpacing: 1,
              color,
              lineHeight: 1,
            }}
          >
            {heading}
          </p>
          <table
            style={{
              width: "100%",
              marginTop: 12,
              borderCollapse: "collapse",
              fontSize: 11,
            }}
          >
            <tbody>
              {meta.map(([k, v]) => (
                <tr key={k}>
                  <td
                    style={{
                      background: "#f3f3f3",
                      border: "1px solid #ddd",
                      padding: "4px 8px",
                      fontWeight: 700,
                      width: "42%",
                    }}
                  >
                    {k}
                  </td>
                  <td
                    style={{
                      border: "1px solid #ddd",
                      padding: "4px 8px",
                      fontWeight: k.includes("DUE") || k.includes("VALID") ? 700 : 400,
                    }}
                  >
                    {v}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </header>

      {doc.status === "void" && (
        <p style={{ color: "#b42318", fontWeight: 700, marginTop: 12 }}>
          VOID — {doc.voidReason}
        </p>
      )}

      <SectionBar color={color}>
        {doc.kind === "letter" ? "TO" : doc.kind === "expense" ? "PAYEE" : "BILL TO"}
      </SectionBar>
      <div style={{ padding: "8px 4px 14px", fontSize: 13, lineHeight: 1.45 }}>
        <div style={{ fontWeight: 700 }}>{party?.name ?? "—"}</div>
        {party?.address && <div>{party.address}</div>}
        {party?.phone && <div>{party.phone}</div>}
        {party?.email && <div>{party.email}</div>}
        {party?.tin && <div>TIN {party.tin}</div>}
      </div>

      {doc.kind === "letter" ? (
        <LetterBody doc={doc} color={color} />
      ) : (
        <>
          <table style={{ width: "100%", borderCollapse: "collapse" }}>
            <thead>
              <tr style={{ background: color, color: "#fff" }}>
                <th style={th(true)}>DESCRIPTION</th>
                <th style={{ ...th(false), width: 70 }}>TAXED</th>
                <th style={{ ...th(false), width: 150, textAlign: "right" }}>AMOUNT</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((item, i) => (
                <tr key={i} style={{ background: i % 2 === 1 ? stripe : "#fff" }}>
                  <td style={td(true)}>{item?.description ?? "\u00a0"}</td>
                  <td style={{ ...td(false), textAlign: "center" }}>
                    {item ? (item.taxed ? "Y" : "") : ""}
                  </td>
                  <td style={{ ...td(false), textAlign: "right", fontVariantNumeric: "tabular-nums" }}>
                    {item ? ugx(lineAmount(item)) : ""}
                  </td>
                </tr>
              ))}
            </tbody>
          </table>

          <div
            style={{
              display: "flex",
              gap: 24,
              marginTop: 0,
              alignItems: "stretch",
            }}
          >
            <div style={{ flex: 1 }}>
              <SectionBar color={color}>OTHER COMMENTS</SectionBar>
              <div style={{ padding: "10px 6px", fontSize: 12, whiteSpace: "pre-wrap" }}>
                {comments || "—"}
              </div>
            </div>
            <div style={{ width: 240, paddingTop: 8 }}>
              <Row label="Subtotal" value={ugx(net)} />
              <Row
                label={doc.vatRate > 0 ? `Tax ${Math.round(doc.vatRate * 100)}%` : "Tax"}
                value={vat > 0 ? ugx(vat) : ""}
              />
              <div
                style={{
                  marginTop: 6,
                  background: color,
                  color: "#fff",
                  fontWeight: 700,
                  display: "flex",
                  justifyContent: "space-between",
                  padding: "8px 10px",
                }}
              >
                <span>Total</span>
                <span>{ugx(total)}</span>
              </div>
              <p style={{ fontSize: 10, marginTop: 8, color: "#444" }}>{branding.paymentNote}</p>
            </div>
          </div>
        </>
      )}

      {doc.efris && (
        <div
          style={{
            marginTop: 22,
            border: `1px solid ${color}`,
            padding: 10,
            display: "flex",
            gap: 12,
            alignItems: "center",
          }}
        >
          <QrMark payload={doc.efris.payload} size={88} />
          <div style={{ fontSize: 11, lineHeight: 1.5 }}>
            <div style={{ fontWeight: 700, color }}>EFRIS fiscal mark</div>
            <div>
              FDN <span style={{ fontFamily: "ui-monospace, monospace" }}>{doc.efris.fdn}</span>
            </div>
            <div>
              Verification{" "}
              <span style={{ fontFamily: "ui-monospace, monospace" }}>{doc.efris.verification}</span>
            </div>
            <div>TIN {branding.tin}</div>
            <div style={{ color: "#666", marginTop: 4 }}>
              Demo fiscalisation for this desk. Live URA accreditation is a later step.
            </div>
          </div>
        </div>
      )}

      <footer style={{ marginTop: 28, textAlign: "center", fontSize: 11, color: "#333" }}>
        <p>
          If you have any questions about this {KIND_META[doc.kind].singular.toLowerCase()}, please
          contact {branding.phone} or {branding.email}.
        </p>
        <p style={{ fontWeight: 700, fontStyle: "italic", fontSize: 14, marginTop: 8 }}>
          Thank You For Your Business!
        </p>
      </footer>
    </article>
  );
}

function LetterBody({ doc, color }: { doc: DocumentRecord; color: string }) {
  return (
    <div>
      <SectionBar color={color}>SUBJECT</SectionBar>
      <p style={{ padding: "8px 4px", fontWeight: 700 }}>{doc.subject || "—"}</p>
      <div
        style={{
          padding: "8px 4px 24px",
          whiteSpace: "pre-wrap",
          lineHeight: 1.65,
          fontSize: 13,
          minHeight: 280,
        }}
      >
        {doc.body}
      </div>
    </div>
  );
}

function SectionBar({ color, children }: { color: string; children: React.ReactNode }) {
  return (
    <div
      style={{
        background: color,
        color: "#fff",
        fontWeight: 700,
        letterSpacing: 0.6,
        padding: "6px 10px",
        fontSize: 12,
      }}
    >
      {children}
    </div>
  );
}

function Row({ label, value }: { label: string; value: string }) {
  return (
    <div
      style={{
        display: "flex",
        justifyContent: "space-between",
        padding: "5px 4px",
        fontSize: 12,
      }}
    >
      <span>{label}</span>
      <span style={{ fontVariantNumeric: "tabular-nums" }}>{value}</span>
    </div>
  );
}

function th(left: boolean): React.CSSProperties {
  return {
    textAlign: left ? "left" : "center",
    padding: "7px 10px",
    fontWeight: 700,
    letterSpacing: 0.4,
    fontSize: 12,
  };
}

function td(left: boolean): React.CSSProperties {
  return {
    textAlign: left ? "left" : "center",
    padding: "8px 10px",
    height: 28,
    borderBottom: "1px solid transparent",
  };
}
