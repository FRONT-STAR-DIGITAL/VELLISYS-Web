import { StyleSheet } from '@react-pdf/renderer';

export function makeStyles(brandColor = '#1E4EFF', deep = '#08143A') {
  return StyleSheet.create({
    page: {
      paddingTop: 36,
      paddingBottom: 48,
      paddingHorizontal: 40,
      fontSize: 10,
      fontFamily: 'Helvetica',
      color: '#111827',
      backgroundColor: '#ffffff',
    },
    letterhead: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'flex-start',
      marginBottom: 18,
      gap: 16,
    },
    logo: {
      width: 110,
      height: 40,
      objectFit: 'contain',
      marginBottom: 6,
    },
    coName: {
      fontSize: 12,
      fontFamily: 'Helvetica-Bold',
      marginBottom: 2,
      color: deep,
    },
    coMeta: {
      fontSize: 8,
      color: '#4b5563',
      lineHeight: 1.4,
      maxWidth: 220,
    },
    titleBlock: {
      alignItems: 'flex-end',
      maxWidth: 220,
    },
    title: {
      fontSize: 20,
      fontFamily: 'Helvetica-Bold',
      color: brandColor,
      marginBottom: 4,
      textAlign: 'right',
    },
    number: {
      fontSize: 11,
      fontFamily: 'Helvetica-Bold',
      textAlign: 'right',
      marginBottom: 6,
    },
    metaTable: {
      marginTop: 2,
    },
    metaRow: {
      flexDirection: 'row',
      justifyContent: 'flex-end',
      gap: 8,
      marginBottom: 2,
    },
    metaKey: {
      fontSize: 8,
      color: '#6b7280',
      width: 70,
      textAlign: 'right',
      textTransform: 'uppercase',
    },
    metaVal: {
      fontSize: 9,
      width: 110,
      textAlign: 'right',
      fontFamily: 'Helvetica-Bold',
    },
    parties: {
      flexDirection: 'row',
      gap: 20,
      marginBottom: 16,
      marginTop: 4,
    },
    partyBox: {
      flex: 1,
      minWidth: 0,
    },
    partyLabel: {
      fontSize: 8,
      fontFamily: 'Helvetica-Bold',
      color: brandColor,
      letterSpacing: 1,
      textTransform: 'uppercase',
      marginBottom: 4,
    },
    partyName: {
      fontSize: 11,
      fontFamily: 'Helvetica-Bold',
      marginBottom: 2,
    },
    partyLine: {
      fontSize: 9,
      color: '#374151',
      lineHeight: 1.35,
    },
    table: {
      marginTop: 4,
      marginBottom: 10,
    },
    tableHeader: {
      flexDirection: 'row',
      backgroundColor: brandColor,
      color: '#ffffff',
      paddingVertical: 6,
      paddingHorizontal: 6,
    },
    th: {
      fontSize: 8,
      fontFamily: 'Helvetica-Bold',
      color: '#ffffff',
      textTransform: 'uppercase',
    },
    tableRow: {
      flexDirection: 'row',
      paddingVertical: 6,
      paddingHorizontal: 6,
      borderBottomWidth: 0.5,
      borderBottomColor: '#e5e7eb',
      alignItems: 'flex-start',
    },
    tableRowAlt: {
      backgroundColor: '#f8fafc',
    },
    td: {
      fontSize: 9,
      color: '#111827',
    },
    colItem: { width: '22%' },
    colDesc: { width: '30%' },
    colQty: { width: '10%', textAlign: 'right' },
    colRate: { width: '14%', textAlign: 'right' },
    colTotal: { width: '14%', textAlign: 'right' },
    colVat: { width: '10%', textAlign: 'right' },
    totalsWrap: {
      marginTop: 8,
      alignItems: 'flex-end',
    },
    totalsBox: {
      width: 220,
    },
    totalRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      paddingVertical: 3,
    },
    totalLabel: {
      fontSize: 9,
      color: '#4b5563',
    },
    totalValue: {
      fontSize: 9,
      fontFamily: 'Helvetica-Bold',
    },
    grandRow: {
      flexDirection: 'row',
      justifyContent: 'space-between',
      backgroundColor: brandColor,
      color: '#fff',
      paddingVertical: 7,
      paddingHorizontal: 8,
      marginTop: 4,
    },
    grandLabel: {
      fontSize: 10,
      fontFamily: 'Helvetica-Bold',
      color: '#fff',
    },
    grandValue: {
      fontSize: 10,
      fontFamily: 'Helvetica-Bold',
      color: '#fff',
    },
    notes: {
      marginTop: 14,
      fontSize: 9,
      color: '#374151',
      lineHeight: 1.4,
      maxWidth: '58%',
    },
    notesLabel: {
      fontFamily: 'Helvetica-Bold',
      color: brandColor,
      marginBottom: 2,
      fontSize: 8,
      textTransform: 'uppercase',
      letterSpacing: 0.8,
    },
    signRow: {
      marginTop: 28,
      flexDirection: 'row',
      justifyContent: 'space-between',
      alignItems: 'flex-end',
    },
    signCol: {
      width: '42%',
    },
    signImg: {
      width: 120,
      height: 40,
      objectFit: 'contain',
      marginBottom: 4,
    },
    signLine: {
      borderBottomWidth: 1,
      borderBottomColor: '#111827',
      marginBottom: 4,
      height: 28,
    },
    signLabel: {
      fontSize: 9,
      color: '#374151',
    },
    authBox: {
      flexDirection: 'row',
      alignItems: 'center',
      gap: 8,
      borderWidth: 1,
      borderColor: '#d1d5db',
      borderRadius: 4,
      padding: 6,
      alignSelf: 'center',
      marginTop: 20,
    },
    qr: {
      width: 48,
      height: 48,
    },
    authMeta: {
      maxWidth: 160,
    },
    authHint: {
      fontSize: 7,
      color: '#6b7280',
      textTransform: 'uppercase',
      letterSpacing: 0.4,
      marginBottom: 2,
    },
    authPowered: {
      fontSize: 7,
      color: '#4b5563',
      fontFamily: 'Helvetica-Bold',
    },
    letterBody: {
      marginTop: 16,
      fontSize: 10,
      lineHeight: 1.5,
      color: '#111827',
    },
    subject: {
      marginTop: 10,
      marginBottom: 8,
      fontSize: 11,
      fontFamily: 'Helvetica-Bold',
    },
    footerThanks: {
      marginTop: 10,
      fontSize: 9,
      color: '#6b7280',
      textAlign: 'center',
    },
  });
}

export type PdfStyles = ReturnType<typeof makeStyles>;
