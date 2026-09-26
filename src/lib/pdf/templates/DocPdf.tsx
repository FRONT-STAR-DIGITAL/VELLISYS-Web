import React from 'react';
import { Document, Page, Text, View } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import { makeStyles } from '../styles';
import { Letterhead } from '../components/Letterhead';
import { PartyBox } from '../components/PartyBox';
import { InvoicePdf } from './InvoicePdf';
import { Signatures } from '../components/Signatures';

type Props = {
  docType: string;
  doc: FullDocument;
  qrDataUrl?: string;
};

function LetterPdf({ doc, qrDataUrl }: { doc: FullDocument; qrDataUrl?: string }) {
  const styles = makeStyles(doc.brand.color, doc.brand.colorDeep);
  return (
    <Document title={doc.number} author={doc.brand.name || 'Vellisys'}>
      <Page size="A4" style={styles.page} wrap>
        <Letterhead doc={doc} styles={styles} />
        <View style={styles.parties}>
          <PartyBox title="TO" party={doc.client} styles={styles} />
        </View>
        {doc.subject ? <Text style={styles.subject}>Re: {doc.subject}</Text> : null}
        <Text style={styles.letterBody}>{doc.body || ''}</Text>
        <Signatures doc={doc} styles={styles} qrDataUrl={qrDataUrl} />
      </Page>
    </Document>
  );
}

export function DocPdf({ docType, doc, qrDataUrl }: Props) {
  const kind = (docType || doc.kind || 'invoice').toLowerCase();
  if (kind === 'letter' || kind === 'custom') {
    return <LetterPdf doc={doc} qrDataUrl={qrDataUrl} />;
  }
  // Quotation, invoice, receipt, expense, delivery, refund, return_note share money layout
  return <InvoicePdf doc={doc} qrDataUrl={qrDataUrl} />;
}
