import React from 'react';
import { pdf } from '@react-pdf/renderer';
import QRCode from 'qrcode';
import type { FullDocument } from './types';
import { DocPdf } from './templates/DocPdf';
import { ensurePdfFonts } from './fonts';

export type { FullDocument } from './types';

function triggerBlobDownload(blob: Blob, filename: string): void {
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = filename;
  a.rel = 'noopener';
  document.body.appendChild(a);
  a.click();
  a.remove();
  setTimeout(() => URL.revokeObjectURL(url), 1500);
}

export function canExportDocument(doc: FullDocument): boolean {
  if (doc.canExport === false) return false;
  const status = (doc.status || '').toLowerCase();
  return status !== '' && status !== 'void';
}

export async function downloadDocumentPdf(docType: string, doc: FullDocument): Promise<void> {
  if (!canExportDocument(doc)) {
    throw new Error('This document cannot be exported (void or unavailable).');
  }
  ensurePdfFonts();
  const verifyUrl =
    doc.verifyUrl
    || `${window.location.origin}/verify.php?id=${doc.id}&t=${encodeURIComponent(doc.verifyToken || '')}`;
  let qrDataUrl = '';
  try {
    qrDataUrl = await QRCode.toDataURL(verifyUrl, { margin: 1, width: 220, errorCorrectionLevel: 'M' });
  } catch {
    qrDataUrl = '';
  }
  const element = React.createElement(DocPdf, { docType, doc, qrDataUrl });
  const blob = await pdf(element).toBlob();
  const number = (doc.number || 'document').replace(/[^\w.-]+/g, '-');
  triggerBlobDownload(blob, `VELLISYS-${number}.pdf`);
}

/** Alias for statement-style downloads (same pipeline). */
export async function downloadStatementPdf(doc: FullDocument): Promise<void> {
  return downloadDocumentPdf('statement', doc);
}

const api = {
  downloadDocumentPdf,
  downloadStatementPdf,
  canExportDocument,
};

declare global {
  interface Window {
    VellisysPdf?: typeof api;
  }
}

if (typeof window !== 'undefined') {
  window.VellisysPdf = api;
}
