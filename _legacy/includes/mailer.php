<?php
/**
 * includes/mailer.php — outgoing mail, with no Composer package required.
 *
 * PHP's own mail() does not work on a stock XAMPP for Windows, so this speaks
 * SMTP directly over a stream socket. Two transports:
 *
 *   smtp  real delivery through the mailbox set in config
 *   log   writes the message to /sessions/mail as an .eml file and hands the
 *         caller the link, so registration can be demonstrated offline
 *
 * Nothing here interpolates caller data into a header line without stripping
 * CR/LF first — that is what stops header injection through a name field.
 */

declare(strict_types=1);

/**
 * Send one message.
 *
 * @return array{ok:bool, message:string, logged_to:?string}
 */
function send_mail(string $toEmail, string $toName, string $subject, string $html, string $text = ''): array
{
    $toEmail = trim($toEmail);

    if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'message' => 'That email address is not deliverable.', 'logged_to' => null];
    }

    $text    = $text !== '' ? $text : trim(html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8'));
    $message = build_mime_message($toEmail, $toName, $subject, $html, $text);

    if (MAIL_TRANSPORT !== 'smtp' || SMTP_USERNAME === '') {
        return mail_to_log($toEmail, $subject, $message);
    }

    try {
        smtp_deliver($toEmail, $message);
        return ['ok' => true, 'message' => 'Email sent.', 'logged_to' => null];
    } catch (RuntimeException $ex) {
        error_log('[e-RESERVE] mail: ' . $ex->getMessage());
        /* Never lose the message because the mail server was unreachable. */
        $fallback = mail_to_log($toEmail, $subject, $message);
        return [
            'ok'        => false,
            'message'   => 'The confirmation email could not be sent right now.',
            'logged_to' => $fallback['logged_to'],
        ];
    }
}

/** Strip anything that could start a new header line. */
function mail_header_safe(string $value): string
{
    return trim(str_replace(["\r", "\n", "\0"], '', $value));
}

/** RFC 2047 encoding so accents in a name survive the trip. */
function mail_encode_header(string $value): string
{
    $value = mail_header_safe($value);
    return preg_match('/[^\x20-\x7E]/', $value)
        ? '=?UTF-8?B?' . base64_encode($value) . '?='
        : $value;
}

/** Assemble a multipart/alternative message. */
function build_mime_message(string $toEmail, string $toName, string $subject, string $html, string $text): string
{
    $boundary = 'erv' . bin2hex(random_bytes(12));
    $to       = $toName !== ''
        ? sprintf('"%s" <%s>', mail_encode_header($toName), mail_header_safe($toEmail))
        : mail_header_safe($toEmail);

    $headers = [
        'Date: ' . date('r'),
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@e-reserve.local>',
        'From: "' . mail_encode_header(MAIL_FROM_NAME) . '" <' . mail_header_safe(MAIL_FROM) . '>',
        'To: ' . $to,
        'Subject: ' . mail_encode_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
    ];

    $body = "--$boundary\r\n"
          . "Content-Type: text/plain; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($text)) . "\r\n"
          . "--$boundary\r\n"
          . "Content-Type: text/html; charset=UTF-8\r\n"
          . "Content-Transfer-Encoding: base64\r\n\r\n"
          . chunk_split(base64_encode($html)) . "\r\n"
          . "--$boundary--\r\n";

    return implode("\r\n", $headers) . "\r\n\r\n" . $body;
}

/** Development transport: keep the message on disk. */
function mail_to_log(string $toEmail, string $subject, string $message): array
{
    $dir = STORAGE_PATH . '/mail';

    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        return ['ok' => false, 'message' => 'Mail could not be written.', 'logged_to' => null];
    }

    $name = date('Ymd-His') . '-' . substr(preg_replace('/[^a-z0-9]/i', '', $toEmail) ?? 'x', 0, 24)
          . '-' . bin2hex(random_bytes(4)) . '.eml';

    file_put_contents($dir . '/' . $name, $message);

    return ['ok' => true, 'message' => 'Email written to the mail log.', 'logged_to' => $name];
}

/* ==========================================================================
 | Minimal SMTP client
 * ========================================================================*/

function smtp_deliver(string $toEmail, string $message): void
{
    $host = SMTP_ENCRYPTION === 'ssl' ? 'ssl://' . SMTP_HOST : SMTP_HOST;

    $socket = @stream_socket_client(
        $host . ':' . SMTP_PORT,
        $errNo,
        $errStr,
        SMTP_TIMEOUT,
        STREAM_CLIENT_CONNECT,
        stream_context_create(['ssl' => ['SNI_enabled' => true]])
    );

    if ($socket === false) {
        throw new RuntimeException('cannot reach ' . SMTP_HOST . ':' . SMTP_PORT . ' — ' . $errStr);
    }

    stream_set_timeout($socket, SMTP_TIMEOUT);

    try {
        smtp_expect($socket, 220);

        $ehlo = 'e-reserve.local';
        smtp_send($socket, 'EHLO ' . $ehlo);
        smtp_expect($socket, 250);

        if (SMTP_ENCRYPTION === 'tls') {
            smtp_send($socket, 'STARTTLS');
            smtp_expect($socket, 220);

            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('STARTTLS negotiation failed');
            }

            /* The handshake resets what the server told us, so greet again. */
            smtp_send($socket, 'EHLO ' . $ehlo);
            smtp_expect($socket, 250);
        }

        smtp_send($socket, 'AUTH LOGIN');
        smtp_expect($socket, 334);
        smtp_send($socket, base64_encode(SMTP_USERNAME));
        smtp_expect($socket, 334);
        smtp_send($socket, base64_encode(SMTP_PASSWORD));
        smtp_expect($socket, 235);

        smtp_send($socket, 'MAIL FROM:<' . mail_header_safe(MAIL_FROM) . '>');
        smtp_expect($socket, 250);
        smtp_send($socket, 'RCPT TO:<' . mail_header_safe($toEmail) . '>');
        smtp_expect($socket, [250, 251]);

        smtp_send($socket, 'DATA');
        smtp_expect($socket, 354);

        /* A lone "." would end the message early, so escape it. */
        $body = preg_replace('/^\./m', '..', $message);
        fwrite($socket, $body . "\r\n.\r\n");
        smtp_expect($socket, 250);

        smtp_send($socket, 'QUIT');
    } finally {
        fclose($socket);
    }
}

function smtp_send($socket, string $line): void
{
    if (fwrite($socket, $line . "\r\n") === false) {
        throw new RuntimeException('write failed');
    }
}

/** Read a reply, following multi-line continuations, and check the code. */
function smtp_expect($socket, $expected): string
{
    $expected = (array) $expected;
    $reply    = '';

    while (($line = fgets($socket, 515)) !== false) {
        $reply .= $line;
        /* "250-" means more lines follow; "250 " is the last one. */
        if (strlen($line) < 4 || $line[3] !== '-') {
            break;
        }
    }

    if ($reply === '') {
        throw new RuntimeException('no reply from the mail server');
    }

    $code = (int) substr($reply, 0, 3);

    if (!in_array($code, $expected, true)) {
        throw new RuntimeException('unexpected reply: ' . trim($reply));
    }

    return $reply;
}

/* ==========================================================================
 | Templates
 * ========================================================================*/

/** The confirmation email a new student receives. */
function verification_email_html(string $name, string $link): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
    $hours    = (int) VERIFY_TOKEN_HOURS;

    return <<<HTML
<!DOCTYPE html>
<html><body style="margin:0;padding:0;background:#F6FAF8;font-family:'Segoe UI',Arial,sans-serif;color:#10241D;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="padding:28px 12px;">
    <tr><td align="center">
      <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
             style="max-width:540px;background:#ffffff;border:1px solid #E3EDE8;border-radius:16px;overflow:hidden;">
        <tr><td style="background:#0B3B2C;padding:26px 30px;">
          <div style="font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-.5px;">
            e-<span style="color:#F4BC5B;">RESERVE</span>
          </div>
          <div style="font-size:11px;letter-spacing:2px;text-transform:uppercase;color:rgba(255,255,255,.55);margin-top:3px;">
            SIGLA 2026
          </div>
        </td></tr>

        <tr><td style="padding:30px;">
          <h1 style="margin:0 0 12px;font-size:19px;color:#06251B;">Confirm your email address</h1>
          <p style="margin:0 0 16px;font-size:14px;line-height:1.7;color:#4A5F58;">
            Hi {$safeName}, thanks for registering for SIGLA: A Student's Night 2026.
            Confirm this address so we know the account is really yours — you will need it
            before you can upload your documents and get your ticket.
          </p>

          <p style="margin:0 0 24px;">
            <a href="{$safeLink}"
               style="display:inline-block;background:#E9A93A;color:#3B2405;font-weight:800;font-size:14px;
                      text-decoration:none;padding:13px 26px;border-radius:99px;">
              Confirm my email
            </a>
          </p>

          <p style="margin:0 0 8px;font-size:12px;color:#7C8F88;">
            The link expires in {$hours} hours. If the button does not work, paste this into your browser:
          </p>
          <p style="margin:0 0 20px;font-size:12px;word-break:break-all;">
            <a href="{$safeLink}" style="color:#B87A18;">{$safeLink}</a>
          </p>

          <p style="margin:0;padding-top:18px;border-top:1px solid #E3EDE8;font-size:12px;color:#7C8F88;line-height:1.7;">
            If you did not create this account, ignore this message — no ticket was
            issued and nothing was submitted in your name.
          </p>
        </td></tr>

        <tr><td style="background:#F6FAF8;padding:16px 30px;font-size:11px;color:#7C8F88;text-align:center;">
          Supreme Student Council · Batangas State University TNEU – ARASOF Nasugbu
        </td></tr>
      </table>
    </td></tr>
  </table>
</body></html>
HTML;
}

function verification_email_text(string $name, string $link): string
{
    return "Hi {$name},\n\n"
         . "Confirm your email address to finish setting up your e-RESERVE account for "
         . EVENT_NAME . ".\n\n"
         . $link . "\n\n"
         . 'The link expires in ' . (int) VERIFY_TOKEN_HOURS . " hours.\n\n"
         . "If you did not create this account, ignore this message.\n\n"
         . "Supreme Student Council\n"
         . EVENT_HOST;
}
