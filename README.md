# Counterfoil

The receipt book that keeps your books.

Counterfoil is an annual-subscription record-keeping desk for Ugandan corporates. It is modelled on the indigenous stationery still sold in Kampala — sequential quotation, invoice, official receipt, supplier bill and payment voucher pads, with original / duplicate / counterfoil copies — and it writes the debtors and creditors ledgers from those pages.

This repository is a working slice: a seeded company (Pearl Traders Ltd, Industrial Area, Kampala), live books you can issue into, and aged debtors and creditors in Uganda shillings.

## The catch

Western accounting products start from journals and charts of accounts. Ugandan cashiers start from a numbered pad. The product is the pad: locked sequential numbers, amount in words, TIN and 18% VAT, void-not-delete, and ledgers that fall out of the counterfoils instead of a second book nobody updates.

The commercial motion is also indigenous. Companies already buy the year in books from the stationer. Counterfoil is that purchase, once a year.

## Run locally

```bash
npm install
npm run dev
```

Open [http://127.0.0.1:43217](http://127.0.0.1:43217).

The demo stores what you issue in the browser (`localStorage`). Use **Restore demo books** on the desk to return to the seeded year.

## What is in the slice

- Landing that states the catch
- Five books, next number locked at issue
- Stationery that switches original / duplicate / counterfoil
- Convert quotation → invoice
- Receipt against invoice, voucher against bill
- Void a spoiled page (number stays)
- Debtors and creditors with current / 30 / 60 / 90 aging
- Annual subscription copy (no payments in this demo)

Not in this slice: multi-user auth, a database, URA eFRIS fiscalisation, or live mobile-money collection.
