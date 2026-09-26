import React from 'react';
import { View, Text } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import type { PdfStyles } from '../styles';
import { formatMoney, formatQty } from '../money';

type Props = {
  doc: FullDocument;
  styles: PdfStyles;
};

function Header({ styles }: { styles: PdfStyles }) {
  return (
    <View style={styles.tableHeader} fixed>
      <Text style={[styles.th, styles.colItem]}>Item</Text>
      <Text style={[styles.th, styles.colDesc]}>Description</Text>
      <Text style={[styles.th, styles.colQty]}>Qty</Text>
      <Text style={[styles.th, styles.colRate]}>Unit price</Text>
      <Text style={[styles.th, styles.colTotal]}>Total Amt</Text>
      <Text style={[styles.th, styles.colVat]}>VAT</Text>
    </View>
  );
}

export function MoneyTable({ doc, styles }: Props) {
  const lines = doc.lines?.length ? doc.lines : [];
  return (
    <View style={styles.table}>
      <Header styles={styles} />
      {lines.map((line, i) => {
        const vat = line.taxable && line.taxRate > 0
          ? Math.round(line.lineTotal * line.taxRate * 100) / 100
          : 0;
        return (
          <View
            key={`${i}-${line.description}`}
            style={i % 2 ? [styles.tableRow, styles.tableRowAlt] : styles.tableRow}
            wrap={false}
          >
            <Text style={[styles.td, styles.colItem]}>{line.itemName || '—'}</Text>
            <Text style={[styles.td, styles.colDesc]}>{line.itemDescription || line.description || '—'}</Text>
            <Text style={[styles.td, styles.colQty]}>{formatQty(line.quantity)}</Text>
            <Text style={[styles.td, styles.colRate]}>{formatMoney(line.unitPrice, doc.currency)}</Text>
            <Text style={[styles.td, styles.colTotal]}>{formatMoney(line.lineTotal, doc.currency)}</Text>
            <Text style={[styles.td, styles.colVat]}>{vat > 0 ? formatMoney(vat, doc.currency) : '—'}</Text>
          </View>
        );
      })}
    </View>
  );
}
