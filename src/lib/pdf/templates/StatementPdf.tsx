import React from 'react';
import { Document, Page, Text, View } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import { makeStyles } from '../styles';
import { Letterhead } from '../components/Letterhead';
import { PartyBox } from '../components/PartyBox';
import { MoneyTable } from '../components/MoneyTable';
import { Totals } from '../components/Totals';
import { Signatures } from '../components/Signatures';

/** Generic statement-style sheet (same family as invoice). */
export function StatementPdf({ doc, qrDataUrl }: { doc: FullDocument; qrDataUrl?: string }) {
  const styles = makeStyles(doc.brand.color, doc.brand.colorDeep);
  return (
    <Document title={doc.number} author={doc.brand.name || 'Vellisys'}>
      <Page size="A4" style={styles.page} wrap>
        <Letterhead doc={doc} styles={styles} />
        <View style={styles.parties}>
          <PartyBox title="TO" party={doc.client} styles={styles} />
        </View>
        <MoneyTable doc={doc} styles={styles} />
        <Totals doc={doc} styles={styles} />
        <Signatures doc={doc} styles={styles} qrDataUrl={qrDataUrl} />
      </Page>
    </Document>
  );
}
