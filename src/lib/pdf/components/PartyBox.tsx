import React from 'react';
import { View, Text } from '@react-pdf/renderer';
import type { FullParty } from '../types';
import type { PdfStyles } from '../styles';

type Props = {
  title: string;
  party: FullParty;
  styles: PdfStyles;
};

export function PartyBox({ title, party, styles }: Props) {
  return (
    <View style={styles.partyBox}>
      <Text style={styles.partyLabel}>{title}</Text>
      {party.name ? <Text style={styles.partyName}>{party.name}</Text> : null}
      {party.contact ? <Text style={styles.partyLine}>Attn: {party.contact}</Text> : null}
      {party.phone ? <Text style={styles.partyLine}>Tel: {party.phone}</Text> : null}
      {party.email ? <Text style={styles.partyLine}>Email: {party.email}</Text> : null}
      {party.address ? <Text style={styles.partyLine}>{party.address}</Text> : null}
      {party.tin ? <Text style={styles.partyLine}>TIN: {party.tin}</Text> : null}
    </View>
  );
}
