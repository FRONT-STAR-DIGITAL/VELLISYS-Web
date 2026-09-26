import React from 'react';
import { View, Text, Image } from '@react-pdf/renderer';
import type { FullDocument } from '../types';
import type { PdfStyles } from '../styles';
import { formatDateIso } from '../money';

type Props = {
  doc: FullDocument;
  styles: PdfStyles;
};

export function Letterhead({ doc, styles }: Props) {
  const b = doc.brand;
  const lines = [b.address, b.city, [b.phone, b.email].filter(Boolean).join(' · '), b.tin ? `TIN ${b.tin}` : '']
    .filter(Boolean)
    .join('\n');

  return (
    <View style={styles.letterhead}>
      <View>
        {b.logoUrl ? <Image src={b.logoUrl} style={styles.logo} /> : null}
        <Text style={styles.coName}>{b.name || 'Company'}</Text>
        {lines ? <Text style={styles.coMeta}>{lines}</Text> : null}
      </View>
      <View style={styles.titleBlock}>
        <Text style={styles.title}>{doc.heading || doc.kindLabel}</Text>
        <Text style={styles.number}>{doc.number}</Text>
        <View style={styles.metaTable}>
          <View style={styles.metaRow}>
            <Text style={styles.metaKey}>Date</Text>
            <Text style={styles.metaVal}>{formatDateIso(doc.issueDate)}</Text>
          </View>
          {doc.dueDate ? (
            <View style={styles.metaRow}>
              <Text style={styles.metaKey}>Due</Text>
              <Text style={styles.metaVal}>{formatDateIso(doc.dueDate)}</Text>
            </View>
          ) : null}
          <View style={styles.metaRow}>
            <Text style={styles.metaKey}>Currency</Text>
            <Text style={styles.metaVal}>{doc.currency}</Text>
          </View>
          {doc.statusLabel ? (
            <View style={styles.metaRow}>
              <Text style={styles.metaKey}>Status</Text>
              <Text style={styles.metaVal}>{doc.statusLabel}</Text>
            </View>
          ) : null}
        </View>
      </View>
    </View>
  );
}
