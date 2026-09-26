import React from 'react';
import { View, Text } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import type { PdfStyles } from '../styles';
import { formatMoney } from '../money';

type Props = {
  doc: FullDocument;
  styles: PdfStyles;
};

export function Totals({ doc, styles }: Props) {
  const showTax = (doc.taxTotal || 0) > 0.0001;
  const showPaid = (doc.amountPaid || 0) > 0.0001;
  const showBal = typeof doc.balanceDue === 'number';

  return (
    <View style={styles.totalsWrap} wrap={false}>
      <View style={styles.totalsBox}>
        <View style={styles.totalRow}>
          <Text style={styles.totalLabel}>Subtotal</Text>
          <Text style={styles.totalValue}>{formatMoney(doc.subtotal, doc.currency)}</Text>
        </View>
        {showTax ? (
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>
              VAT{doc.taxRate ? ` ${Math.round(doc.taxRate * 100)}%` : ''}
            </Text>
            <Text style={styles.totalValue}>{formatMoney(doc.taxTotal, doc.currency)}</Text>
          </View>
        ) : null}
        <View style={styles.grandRow}>
          <Text style={styles.grandLabel}>Total</Text>
          <Text style={styles.grandValue}>{formatMoney(doc.total, doc.currency)}</Text>
        </View>
        {showPaid ? (
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Received / Paid</Text>
            <Text style={styles.totalValue}>{formatMoney(doc.amountPaid || 0, doc.currency)}</Text>
          </View>
        ) : null}
        {showBal ? (
          <View style={styles.totalRow}>
            <Text style={styles.totalLabel}>Balance due</Text>
            <Text style={styles.totalValue}>{formatMoney(doc.balanceDue || 0, doc.currency)}</Text>
          </View>
        ) : null}
      </View>
    </View>
  );
}
