import React from 'react';
import { View, Text, Image } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import type { PdfStyles } from '../styles';

type Props = {
  doc: FullDocument;
  styles: PdfStyles;
  qrDataUrl?: string;
};

export function Signatures({ doc, styles, qrDataUrl }: Props) {
  const sig = doc.createdBy?.signaturePath || doc.brand.signatureUrl || '';
  const label = doc.createdBy?.title || 'Authorized by';

  return (
    <View>
      <View style={styles.signRow} wrap={false}>
        <View style={styles.signCol}>
          {sig ? <Image src={sig} style={styles.signImg} /> : <View style={styles.signLine} />}
          <Text style={styles.signLabel}>{label}</Text>
        </View>
        <View style={[styles.signCol, { alignItems: 'flex-end' }]}>
          <Text style={styles.footerThanks}>Thank You For Your Business!</Text>
        </View>
      </View>
      {qrDataUrl ? (
        <View style={styles.authBox} wrap={false}>
          <Image src={qrDataUrl} style={styles.qr} />
          <View style={styles.authMeta}>
            <Text style={styles.authHint}>Scan to verify authenticity</Text>
            <Text style={styles.authPowered}>Powered by www.vellisys.com</Text>
          </View>
        </View>
      ) : null}
    </View>
  );
}
