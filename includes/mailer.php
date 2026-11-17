<?php
/**
 * Lightweight SMTP mailer (no Composer needed). Works with Gmail (app password),
 * Zoho, cPanel mail, Brevo etc. Configure in Admin → Settings → Email.
 * Every message is also recorded in the mail_log table so you can see what was sent.
 * If SMTP is turned off, messages are only logged (useful on localhost).
 */
class Mailer
{
    private $sock;
    private array $transcript = [];

    public static function send(string $to, string $subject, string $html): bool
    {
        $enabled = setting('smtp_enabled', '0') === '1';
        $status = 'logged';
        $error = null;
        if ($enabled) {
            try {
                (new self())->deliver($to, $subject, $html);
                $status = 'sent';
            } catch (Throwable $e) {
                $status = 'failed';
                $error = substr($e->getMessage(), 0, 500);
            }
        }
        try {
            q('INSERT INTO mail_log (recipient, subject, body, status, error) VALUES (?, ?, ?, ?, ?)', [$to, $subject, $html, $status, $error]);
        } catch (Throwable $e) {
        }
        return $status !== 'failed';
    }

    private function deliver(string $to, string $subject, string $html): void
    {
        $host = setting('smtp_host');
        $port = (int)setting('smtp_port', '587');
        $secure = setting('smtp_secure', 'tls');
        $user = setting('smtp_user');
        $pass = setting('smtp_pass');
        $from = setting('mail_from', $user);
        $fromName = setting('mail_from_name', setting('store_name', 'Mora Mori'));
        if (!$host || !$from) throw new RuntimeException('SMTP host / from address not configured.');

        $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $this->sock = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$this->sock) throw new RuntimeException("Connect failed: $errstr ($errno)");
        stream_set_timeout($this->sock, 15);

        $this->expect(220);
        $ehloHost = preg_replace('/[^a-z0-9.\-]/i', '', $_SERVER['HTTP_HOST'] ?? 'localhost') ?: 'localhost';
        $this->cmd("EHLO $ehloHost", 250);
        if ($secure === 'tls') {
            $this->cmd('STARTTLS', 220);
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
                throw new RuntimeException('Could not start TLS.');
            }
            $this->cmd("EHLO $ehloHost", 250);
        }
        if ($user !== '') {
            $this->cmd('AUTH LOGIN', 334);
            $this->cmd(base64_encode($user), 334);
            $this->cmd(base64_encode($pass), 235);
        }
        $this->cmd("MAIL FROM:<$from>", 250);
        $this->cmd("RCPT TO:<$to>", [250, 251]);
        $this->cmd('DATA', 354);

        $boundary = 'b' . bin2hex(random_bytes(8));
        $text = trim(html_entity_decode(strip_tags(preg_replace('/<(br|\/p|\/tr|\/h\d)>/i', "\n", $html)), ENT_QUOTES, 'UTF-8'));
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $this->encode($fromName) . " <$from>",
            "To: <$to>",
            'Subject: ' . $this->encode($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . (explode('@', $from)[1] ?? 'localhost') . '>',
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"$boundary\"",
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--";
        $data = implode("\r\n", $headers) . "\r\n\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data);
        fwrite($this->sock, $data . "\r\n.\r\n");
        $this->expect(250);
        $this->cmd('QUIT', 221);
        fclose($this->sock);
    }

    private function encode(string $s): string
    {
        return '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private function cmd(string $c, $expect): void
    {
        fwrite($this->sock, $c . "\r\n");
        $this->expect($expect);
    }

    private function expect($codes): void
    {
        $codes = (array)$codes;
        $resp = '';
        while (($line = fgets($this->sock, 515)) !== false) {
            $resp .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $code = (int)substr($resp, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($resp ?: 'no response'));
        }
    }
}
