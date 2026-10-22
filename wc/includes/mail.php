<?php
declare(strict_types=1);

/**
 * Mail helpers — prefer SMTP (cPanel / hosting); fall back to PHP mail().
 */

/**
 * @param list<string> $headersExtra Extra header lines (e.g. "Reply-To: a@b.c")
 */
function wms_send_mail(string $to, string $subject, string $body, array $headersExtra = []): bool
{
    $to = trim($to);
    $subject = trim($subject);
    if ($to === '' || $subject === '' || $body === '') {
        wms_mail_log('send aborted: empty to/subject/body');
        return false;
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        wms_mail_log('send aborted: invalid to address');
        return false;
    }

    $from = trim((string) env('CONTACT_INQUIRY_MAIL_FROM', 'info@navodispatch.com'));
    if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
        $from = 'info@navodispatch.com';
    }

    $smtpHost = trim((string) env('CONTACT_INQUIRY_SMTP_HOST', ''));
    if ($smtpHost !== '') {
        return wms_smtp_send($to, $subject, $body, $from, $headersExtra);
    }

    return wms_php_mail_send($to, $subject, $body, $from, $headersExtra);
}

function wms_mail_log(string $message): void
{
    error_log('wms_mail: ' . $message);
}

/**
 * @param list<string> $headersExtra
 */
function wms_php_mail_send(string $to, string $subject, string $body, string $from, array $headersExtra): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . $from,
        'X-Mailer: WMS',
    ];
    foreach ($headersExtra as $line) {
        $line = trim((string) $line);
        if ($line !== '') {
            $headers[] = $line;
        }
    }
    $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $ok = @mail($to, $encodedSubject, $body, implode("\r\n", $headers));
    if (!$ok) {
        wms_mail_log('PHP mail() failed (no local MTA?). Configure CONTACT_INQUIRY_SMTP_HOST.');
    }
    return $ok === true;
}

/**
 * @param list<string> $headersExtra
 */
function wms_smtp_send(string $to, string $subject, string $body, string $from, array $headersExtra): bool
{
    $host = trim((string) env('CONTACT_INQUIRY_SMTP_HOST', ''));
    $port = env_int('CONTACT_INQUIRY_SMTP_PORT', 465);
    $encryption = strtolower(trim((string) env('CONTACT_INQUIRY_SMTP_ENCRYPTION', 'ssl')));
    $user = trim((string) env('CONTACT_INQUIRY_SMTP_USER', $from));
    $pass = (string) env('CONTACT_INQUIRY_SMTP_PASS', '');

    if ($host === '') {
        wms_mail_log('SMTP host empty');
        return false;
    }
    if ($pass === '') {
        wms_mail_log('SMTP password empty — set CONTACT_INQUIRY_SMTP_PASS in wc/.env');
        return false;
    }

    $remote = $host;
    if ($encryption === 'ssl') {
        $remote = 'ssl://' . $host;
    }

    $errno = 0;
    $errstr = '';
    $timeout = 30;
    $fp = @stream_socket_client(
        $remote . ':' . $port,
        $errno,
        $errstr,
        $timeout,
        STREAM_CLIENT_CONNECT
    );
    if (!$fp) {
        wms_mail_log("SMTP connect failed {$host}:{$port} — {$errstr} ({$errno})");
        return false;
    }
    stream_set_timeout($fp, $timeout);

    try {
        if (!wms_smtp_expect($fp, [220])) {
            throw new RuntimeException('bad greeting');
        }

        $ehloHost = 'localhost';
        wms_smtp_cmd($fp, 'EHLO ' . $ehloHost);
        if (!wms_smtp_expect($fp, [250])) {
            wms_smtp_cmd($fp, 'HELO ' . $ehloHost);
            if (!wms_smtp_expect($fp, [250])) {
                throw new RuntimeException('EHLO/HELO failed');
            }
        }

        if ($encryption === 'tls') {
            wms_smtp_cmd($fp, 'STARTTLS');
            if (!wms_smtp_expect($fp, [220])) {
                throw new RuntimeException('STARTTLS failed');
            }
            $crypto = STREAM_CRYPTO_METHOD_TLS_CLIENT;
            if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
                $crypto = STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
            }
            if (!@stream_socket_enable_crypto($fp, true, $crypto)) {
                throw new RuntimeException('TLS negotiate failed');
            }
            wms_smtp_cmd($fp, 'EHLO ' . $ehloHost);
            if (!wms_smtp_expect($fp, [250])) {
                throw new RuntimeException('EHLO after STARTTLS failed');
            }
        }

        wms_smtp_cmd($fp, 'AUTH LOGIN');
        if (!wms_smtp_expect($fp, [334])) {
            throw new RuntimeException('AUTH LOGIN not accepted');
        }
        wms_smtp_cmd($fp, base64_encode($user));
        if (!wms_smtp_expect($fp, [334])) {
            throw new RuntimeException('SMTP user rejected');
        }
        wms_smtp_cmd($fp, base64_encode($pass));
        if (!wms_smtp_expect($fp, [235])) {
            throw new RuntimeException('SMTP auth failed (check CONTACT_INQUIRY_SMTP_USER / PASS)');
        }

        wms_smtp_cmd($fp, 'MAIL FROM:<' . $from . '>');
        if (!wms_smtp_expect($fp, [250])) {
            throw new RuntimeException('MAIL FROM rejected');
        }
        wms_smtp_cmd($fp, 'RCPT TO:<' . $to . '>');
        if (!wms_smtp_expect($fp, [250, 251])) {
            throw new RuntimeException('RCPT TO rejected');
        }
        wms_smtp_cmd($fp, 'DATA');
        if (!wms_smtp_expect($fp, [354])) {
            throw new RuntimeException('DATA not accepted');
        }

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $headerLines = [
            'Date: ' . gmdate('D, d M Y H:i:s') . ' +0000',
            'From: ' . $from,
            'To: ' . $to,
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: 8bit',
            'X-Mailer: WMS',
        ];
        foreach ($headersExtra as $line) {
            $line = trim((string) $line);
            if ($line !== '') {
                $headerLines[] = $line;
            }
        }

        // Dot-stuff body lines that start with "."
        $safeBody = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $body)) ?? $body;
        $safeBody = str_replace("\n", "\r\n", $safeBody);

        $message = implode("\r\n", $headerLines) . "\r\n\r\n" . $safeBody . "\r\n.";
        fwrite($fp, $message . "\r\n");
        if (!wms_smtp_expect($fp, [250])) {
            throw new RuntimeException('message not accepted');
        }

        wms_smtp_cmd($fp, 'QUIT');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        wms_mail_log('SMTP error: ' . $e->getMessage());
        if (is_resource($fp)) {
            fclose($fp);
        }
        return false;
    }
}

/**
 * @param resource $fp
 */
function wms_smtp_cmd($fp, string $cmd): void
{
    fwrite($fp, $cmd . "\r\n");
}

/**
 * @param resource $fp
 * @param list<int> $okCodes
 */
function wms_smtp_expect($fp, array $okCodes): bool
{
    $response = '';
    while (($line = fgets($fp, 515)) !== false) {
        $response .= $line;
        // Multi-line: "250-..." continues; "250 " ends
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
        if (!isset($line[3])) {
            break;
        }
    }
    if ($response === '') {
        return false;
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $okCodes, true)) {
        wms_mail_log('SMTP unexpected reply: ' . trim($response));
        return false;
    }
    return true;
}

/**
 * Notify ops of a new contact inquiry. Fail-soft: returns false on mail failure.
 *
 * @param array<string,mixed> $data Validated inquiry data (+ optional id)
 */
function contact_inquiry_notify_email(array $data): bool
{
    if (!env_bool('CONTACT_INQUIRY_MAIL_ENABLED', true)) {
        return false;
    }

    $to = trim((string) env('CONTACT_INQUIRY_MAIL_TO', 'info@navodispatch.com'));
    $subject = trim((string) env('CONTACT_INQUIRY_MAIL_SUBJECT', 'Preferred Service'));
    if ($subject === '') {
        $subject = 'Preferred Service';
    }

    $dash = static function (mixed $v): string {
        $s = trim((string) ($v ?? ''));
        return $s !== '' ? $s : '—';
    };

    $lines = [
        'New contact form submission',
        '===========================',
        '',
    ];
    if (!empty($data['id'])) {
        $lines[] = 'Inquiry ID: ' . (int) $data['id'];
    }
    $lines[] = 'Full Name: ' . $dash($data['full_name'] ?? '');
    $lines[] = 'Company: ' . $dash($data['company_name'] ?? '');
    $lines[] = 'Phone: ' . $dash($data['phone'] ?? '');
    $lines[] = 'Email: ' . $dash($data['email'] ?? '');
    $lines[] = 'Preferred Service: ' . $dash($data['preferred_service_title'] ?? '');

    $custom = is_array($data['custom'] ?? null) ? $data['custom'] : [];
    foreach ($custom as $key => $value) {
        $key = (string) $key;
        if ($key === '' || str_ends_with($key, '_label')) {
            continue;
        }
        if ($value === null || $value === '') {
            continue;
        }
        if (isset($custom[$key . '_label']) && trim((string) $custom[$key . '_label']) !== '') {
            $display = (string) $custom[$key . '_label'];
        } else {
            $display = is_scalar($value) ? (string) $value : (string) json_encode($value);
        }
        $pretty = preg_replace('/^cf_/', '', $key) ?? $key;
        $pretty = str_replace('_', ' ', (string) $pretty);
        $lines[] = ucwords($pretty) . ': ' . $display;
    }

    $lines[] = '';
    $lines[] = 'Message:';
    $lines[] = $dash($data['message'] ?? '');
    $lines[] = '';
    $lines[] = 'Submitted at (UTC): ' . now_utc();

    $body = implode("\n", $lines);
    $extra = [];
    $replyTo = trim((string) ($data['email'] ?? ''));
    if ($replyTo !== '' && filter_var($replyTo, FILTER_VALIDATE_EMAIL)) {
        $extra[] = 'Reply-To: ' . $replyTo;
    }

    $ok = wms_send_mail($to, $subject, $body, $extra);
    if (!$ok) {
        wms_mail_log('contact_inquiry_notify_email: failed to send to ' . $to);
    }
    return $ok;
}
