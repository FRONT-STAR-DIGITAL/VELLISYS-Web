<?php
declare(strict_types=1);

function mail_config(): array
{
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require ROOT_PATH . '/config/mail.php';
    }
    return $cfg;
}

function mail_provider_presets(): array
{
    return [
        'hostinger' => [
            'label' => 'Hostinger',
            'smtp_host' => 'smtp.hostinger.com',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
            'pop_host' => 'pop.hostinger.com',
            'pop_port' => 995,
            'imap_host' => 'imap.hostinger.com',
            'imap_port' => 993,
        ],
        'titan' => [
            'label' => 'Titan',
            'smtp_host' => 'smtp.titan.email',
            'smtp_port' => 465,
            'smtp_secure' => 'ssl',
            'pop_host' => 'pop.titan.email',
            'pop_port' => 995,
            'imap_host' => 'imap.titan.email',
            'imap_port' => 993,
        ],
        'custom' => [
            'label' => 'Custom',
            'smtp_host' => '',
            'smtp_port' => 587,
            'smtp_secure' => 'tls',
            'pop_host' => '',
            'pop_port' => 995,
            'imap_host' => '',
            'imap_port' => 993,
        ],
    ];
}

function mail_encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hash('sha256', (string) (mail_config()['secret'] ?? 'vellisys'), true);
    $iv = random_bytes(16);
    $raw = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $raw === false ? '' : base64_encode($iv . $raw);
}

function mail_decrypt_secret(string $stored): string
{
    if ($stored === '') {
        return '';
    }
    $bin = base64_decode($stored, true);
    if ($bin === false || strlen($bin) < 17) {
        return '';
    }
    $key = hash('sha256', (string) (mail_config()['secret'] ?? 'vellisys'), true);
    $iv = substr($bin, 0, 16);
    $raw = substr($bin, 16);
    $plain = openssl_decrypt($raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

function platform_mail_account(): array
{
    $c = mail_config();
    return [
        'host' => (string) $c['host'],
        'port' => (int) $c['port'],
        'secure' => (string) $c['secure'],
        'username' => (string) $c['username'],
        'password' => (string) $c['password'],
        'from_email' => (string) $c['from_email'],
        'from_name' => (string) $c['from_name'],
    ];
}

function company_mail_account(array $company): ?array
{
    $email = trim((string) ($company['mail_email'] ?? ''));
    $pass = mail_decrypt_secret((string) ($company['mail_password'] ?? ''));
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $pass === '') {
        return null;
    }
    $host = trim((string) ($company['smtp_host'] ?? ''));
    if ($host === '') {
        $preset = mail_provider_presets()[$company['mail_provider'] ?? 'hostinger'] ?? mail_provider_presets()['hostinger'];
        $host = $preset['smtp_host'];
    }
    $name = trim((string) ($company['mail_from_name'] ?? '')) ?: (string) ($company['name'] ?? 'Vellisys desk');
    return [
        'host' => $host,
        'port' => (int) ($company['smtp_port'] ?: 465),
        'secure' => in_array($company['smtp_secure'] ?? 'ssl', ['ssl', 'tls', 'none'], true) ? $company['smtp_secure'] : 'ssl',
        'username' => $email,
        'password' => $pass,
        'from_email' => $email,
        'from_name' => $name,
    ];
}

function smtp_send(array $account, string $to, string $subject, string $html, string $text, string $replyTo = ''): array
{
    $host = trim((string) ($account['host'] ?? ''));
    $port = (int) ($account['port'] ?? 465);
    $secure = (string) ($account['secure'] ?? 'ssl');
    $user = (string) ($account['username'] ?? '');
    $pass = (string) ($account['password'] ?? '');
    $from = (string) ($account['from_email'] ?? $user);
    $fromName = (string) ($account['from_name'] ?? 'Vellisys');
    if ($host === '' || $user === '' || $pass === '' || !filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Mailbox is not fully configured.'];
    }

    $timeout = 20;
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $ctx = stream_context_create([
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
            'SNI_enabled' => true,
            'peer_name' => $host,
        ],
    ]);
    $fp = @stream_socket_client($remote, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        return ['ok' => false, 'error' => 'Could not reach ' . $host . ':' . $port . ($errstr ? ' (' . $errstr . ')' : '') . '.'];
    }
    stream_set_timeout($fp, $timeout);

    $read = static function () use ($fp): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 8192);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $line) use ($fp, $read): string {
        fwrite($fp, $line . "\r\n");
        return $read();
    };
    $expect = static function (string $resp, array $ok) use ($fp): ?string {
        $code = (int) substr($resp, 0, 3);
        if (!in_array($code, $ok, true)) {
            fclose($fp);
            $msg = trim(preg_replace('/^\d{3}[\s-]+/m', '', $resp) ?? $resp);
            $msg = $msg !== '' ? $msg : 'SMTP rejected the command.';
            return $msg;
        }
        return null;
    };

    $hello = preg_replace('/[^A-Za-z0-9.-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost')) ?: 'localhost';
    $err = $expect($read(), [220]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $ehlo = $cmd('EHLO ' . $hello);
    $err = $expect($ehlo, [250]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    if ($secure === 'tls') {
        $err = $expect($cmd('STARTTLS'), [220]);
        if ($err) {
            return ['ok' => false, 'error' => $err];
        }
        $crypto = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        if ($crypto !== true) {
            fclose($fp);
            return ['ok' => false, 'error' => 'STARTTLS failed on ' . $host . '.'];
        }
        $err = $expect($cmd('EHLO ' . $hello), [250]);
        if ($err) {
            return ['ok' => false, 'error' => $err];
        }
    }

    $err = $expect($cmd('AUTH LOGIN'), [334]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $err = $expect($cmd(base64_encode($user)), [334]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $err = $expect($cmd(base64_encode($pass)), [235]);
    if ($err) {
        return ['ok' => false, 'error' => 'Sign-in to the mailbox failed. Check the email and password.'];
    }

    $err = $expect($cmd('MAIL FROM:<' . $from . '>'), [250]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $err = $expect($cmd('RCPT TO:<' . $to . '>'), [250, 251]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $err = $expect($cmd('DATA'), [354]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }

    $payload = smtp_build_message($from, $fromName, $to, $subject, $html, $text, $replyTo);
    fwrite($fp, $payload . "\r\n.\r\n");
    $err = $expect($read(), [250]);
    if ($err) {
        return ['ok' => false, 'error' => $err];
    }
    $cmd('QUIT');
    fclose($fp);
    return ['ok' => true, 'error' => '', 'from' => $from];
}

function smtp_build_message(string $from, string $fromName, string $to, string $subject, string $html, string $text, string $replyTo = ''): string
{
    $boundary = 'vellisys' . bin2hex(random_bytes(8));
    $enc = static function (string $s): string {
        $s = str_replace(["\r", "\n"], ['', ''], $s);
        if (preg_match('/[^\x20-\x7E]/', $s)) {
            return '=?UTF-8?B?' . base64_encode($s) . '?=';
        }
        return $s;
    };
    $fromHeader = sprintf('%s <%s>', $enc($fromName), $from);
    $reply = $replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : $from;
    $headers = [
        'Date: ' . date('r'),
        'From: ' . $fromHeader,
        'To: ' . $to,
        'Reply-To: ' . $reply,
        'Subject: ' . $enc($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: Vellisys',
    ];
    $plain = $text !== '' ? $text : trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    $body = '--' . $boundary . "\r\n"
        . "Content-Type: text/plain; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($plain))
        . '--' . $boundary . "\r\n"
        . "Content-Type: text/html; charset=UTF-8\r\n"
        . "Content-Transfer-Encoding: base64\r\n\r\n"
        . chunk_split(base64_encode($html))
        . '--' . $boundary . "--\r\n";
    $raw = implode("\r\n", $headers) . "\r\n\r\n" . $body;
    $raw = preg_replace('/^\./m', '..', $raw) ?? $raw;
    return str_replace("\n", "\r\n", str_replace("\r\n", "\n", $raw));
}
