<?php
declare(strict_types=1);

namespace App\Services;

final class SmtpMailer
{
    /**
     * @param list<string> $to
     */
    public static function send(array $config, array $to, string $subject, string $textBody): void
    {
        if (!$to) {
            return;
        }
        $host = (string)($config['host'] ?? '');
        $port = (int)($config['port'] ?? 587);
        $username = (string)($config['username'] ?? '');
        $password = (string)($config['password'] ?? '');
        $encryption = (string)($config['encryption'] ?? 'tls');
        $fromEmail = (string)($config['from_email'] ?? $username);
        $fromName = (string)($config['from_name'] ?? '');

        if ($host === '' || $port <= 0 || $username === '' || $password === '') {
            throw new \RuntimeException('smtp_config_incomplete');
        }
        if ($fromEmail === '') {
            $fromEmail = $username;
        }

        $remote = ($encryption === 'ssl') ? ('ssl://' . $host) : $host;
        $fp = @fsockopen($remote, $port, $errno, $errstr, 20);
        if (!$fp) {
            throw new \RuntimeException('smtp_connect_failed: ' . $errstr);
        }
        stream_set_timeout($fp, 20);

        self::expect($fp, 220);
        self::cmd($fp, 'EHLO localhost');
        $ehlo = self::readMultiline($fp);

        if ($encryption === 'tls') {
            if (stripos($ehlo, 'STARTTLS') === false) {
                throw new \RuntimeException('smtp_starttls_not_supported');
            }
            self::cmd($fp, 'STARTTLS');
            self::expect($fp, 220);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new \RuntimeException('smtp_starttls_failed');
            }
            self::cmd($fp, 'EHLO localhost');
            self::readMultiline($fp);
        }

        // AUTH LOGIN
        self::cmd($fp, 'AUTH LOGIN');
        self::expect($fp, 334);
        self::cmd($fp, base64_encode($username));
        self::expect($fp, 334);
        self::cmd($fp, base64_encode($password));
        self::expect($fp, 235);

        self::cmd($fp, 'MAIL FROM:<' . $fromEmail . '>');
        self::expect($fp, 250);

        foreach ($to as $addr) {
            $addr = trim($addr);
            if ($addr === '') continue;
            self::cmd($fp, 'RCPT TO:<' . $addr . '>');
            $code = self::readCode($fp);
            if (!in_array($code, [250, 251], true)) {
                throw new \RuntimeException('smtp_rcpt_failed:' . $code);
            }
        }

        self::cmd($fp, 'DATA');
        self::expect($fp, 354);

        $headers = [];
        $headers[] = 'From: ' . ($fromName !== '' ? self::encodeHeader($fromName) . ' ' : '') . '<' . $fromEmail . '>';
        $headers[] = 'To: ' . implode(', ', $to);
        $headers[] = 'Subject: ' . self::encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=utf-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $data = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n", "\r\n", $textBody);
        // Dot-stuffing
        $data = preg_replace('/\r\n\./', "\r\n..", $data) ?? $data;
        fwrite($fp, $data . "\r\n.\r\n");
        self::expect($fp, 250);

        self::cmd($fp, 'QUIT');
        fclose($fp);
    }

    private static function encodeHeader(string $text): string
    {
        // Basic UTF-8 header encoding
        return '=?UTF-8?B?' . base64_encode($text) . '?=';
    }

    private static function cmd($fp, string $line): void
    {
        fwrite($fp, $line . "\r\n");
    }

    private static function readLine($fp): string
    {
        $line = fgets($fp);
        if ($line === false) {
            throw new \RuntimeException('smtp_read_failed');
        }
        return $line;
    }

    private static function readCode($fp): int
    {
        $line = self::readLine($fp);
        return (int)substr($line, 0, 3);
    }

    private static function expect($fp, int $code): void
    {
        $line = self::readLine($fp);
        $got = (int)substr($line, 0, 3);
        if ($got !== $code) {
            throw new \RuntimeException('smtp_unexpected:' . $got . ' expected:' . $code . ' line:' . trim($line));
        }
    }

    private static function readMultiline($fp): string
    {
        $out = '';
        while (true) {
            $line = self::readLine($fp);
            $out .= $line;
            // Multiline replies have a dash after the code (e.g., 250-)
            if (strlen($line) >= 4 && $line[3] !== '-') {
                break;
            }
        }
        return $out;
    }
}
