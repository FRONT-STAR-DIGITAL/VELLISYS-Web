import React from 'react';
import { Document, Page, View, Text } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import { makeStyles } from '../styles';
import { Letterhead } from '../components/Letterhead';
import { PartyBox } from '../components/PartyBox';
import { MoneyTable } from '../components/MoneyTable';
import { Totals } from '../components/Totals';
import { Signatures } from '../components/Signatures';

type Props = { doc: FullDocument; qrDataUrl?: string };

export function InvoicePdf({ doc, qrDataUrl }: Props) {
  const styles = makeStyles(doc.brand.color, doc.brand.colorDeep);
  const partyTitle = doc.kind === 'expense' ? 'PAYEE' : 'BILL TO';

  return (
    <Document title={doc.number} author={doc.brand.name || 'Vellisys'} subject={doc.kindLabel}>
      <Page size="A4" style={styles.page} wrap>
        <Letterhead doc={doc} styles={styles} />
        <View style={styles.parties}>
          <PartyBox title="FROM" party={{
            name: doc.brand.name,
            email: doc.brand.email,
            phone: doc.brand.phone,
            address: [doc.brand.address, doc.brand.city].filter(Boolean).join('\n'),
            tin: doc.brand.tin,
          }} styles={styles} />
          <PartyBox title={partyTitle} party={doc.client} styles={styles} />
        </View>
        <MoneyTable doc={doc} styles={styles} />
        <Totals doc={doc} styles={styles} />
        {doc.notes ? (
          <View style={styles.notes}>
            <Text style={styles.notesLabel}>Other comments</Text>
            <Text>{doc.notes}</Text>
          </View>
        ) : null}
        <Signatures doc={doc} styles={styles} qrDataUrl={qrDataUrl} />
      </Page>
    </Document>
  );
}
