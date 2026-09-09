# Folio

Annual branded books for Ugandan SMEs.

Folio issues quotations, invoices, receipts, expenses and headed letters in the company’s own logo and colour — the same kind of PDF as a Kampala client-facing invoice. The SME plan adds VAT 18% and an EFRIS-style fiscal mark. There is no stock module. Records download as PDF or go out by email.

## Plans

- Starter — UGX 150,000 / year
- SME — UGX 250,000 / year (VAT + EFRIS marks)
- Office — UGX 350,000 / year (three people)

This demo does not take payment. EFRIS marks are generated in the browser for the look of a fiscal invoice; they are not live URA accreditation.

## Run

```bash
npm install
npm run dev
```

Open [http://127.0.0.1:43217](http://127.0.0.1:43217).

The desk is seeded as **Ofagros Limited**, matching the sample invoice. Change logo and colour under Branding. Data stays in the browser (`localStorage`). Restore demo from the desk.
