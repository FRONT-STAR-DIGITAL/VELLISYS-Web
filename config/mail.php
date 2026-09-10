<?php
/**
 * Platform mailbox (super admin only). Hostinger hPanel defaults.
 * Override with FOLIO_SMTP_* environment variables on the live host if needed.
 */
return [
    'host' => getenv('FOLIO_SMTP_HOST') ?: 'smtp.hostinger.com',
    'port' => (int) (getenv('FOLIO_SMTP_PORT') ?: 465),
    'secure' => getenv('FOLIO_SMTP_SECURE') ?: 'ssl',
    'username' => getenv('FOLIO_SMTP_USER') ?: 'info@vellisys.com',
    'password' => getenv('FOLIO_SMTP_PASS') !== false && getenv('FOLIO_SMTP_PASS') !== ''
        ? (string) getenv('FOLIO_SMTP_PASS')
        : 'Vellums@7',
    'from_email' => getenv('FOLIO_SMTP_FROM') ?: 'info@vellisys.com',
    'from_name' => getenv('FOLIO_SMTP_FROM_NAME') ?: 'Vellisys',
    'pop_host' => getenv('FOLIO_POP_HOST') ?: 'pop.hostinger.com',
    'pop_port' => (int) (getenv('FOLIO_POP_PORT') ?: 995),
    'imap_host' => getenv('FOLIO_IMAP_HOST') ?: 'imap.hostinger.com',
    'imap_port' => (int) (getenv('FOLIO_IMAP_PORT') ?: 993),
    'secret' => getenv('FOLIO_MAIL_SECRET') ?: 'vellisys-hostinger-mail-2026',
];
