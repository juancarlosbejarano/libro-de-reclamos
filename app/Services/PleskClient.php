<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Env;

final class PleskClient
{
    /** @return array{ok:bool, status:int, body:string, error?:string} */
    public static function createDomainAlias(string $siteName, string $aliasDomain): array
    {
        $url = (string)(Env::get('PLESK_API_URL', '') ?? '');
        $key = (string)(Env::get('PLESK_API_KEY', '') ?? '');
        $verifyTls = Env::bool('PLESK_VERIFY_TLS', true);

        if ($url === '' || $key === '') {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'PLESK_API_URL/PLESK_API_KEY missing'];
        }

        $aliasDomain = DomainVerifier::normalizeDomain($aliasDomain);
        $siteName = DomainVerifier::normalizeDomain($siteName);

        // Plesk XML API (agent.php). Common packet to add site alias.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<packet version="1.6.9.0">'
            . '<site-alias><add>'
            . '<site-name>' . htmlspecialchars($siteName, ENT_XML1) . '</site-name>'
            . '<name>' . htmlspecialchars($aliasDomain, ENT_XML1) . '</name>'
            . '</add></site-alias>'
            . '</packet>';

        return self::postXml($url, $key, $xml, $verifyTls);
    }

    /** @return array{ok:bool, status:int, body:string, error?:string} */
    private static function postXml(string $url, string $apiKey, string $xml, bool $verifyTls): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init_failed'];
            }
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $xml,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml',
                    // Plesk supports API keys via X-API-Key (common) and KEY (legacy XML API header)
                    'X-API-Key: ' . $apiKey,
                    'KEY: ' . $apiKey,
                ],
                CURLOPT_SSL_VERIFYPEER => $verifyTls,
                CURLOPT_SSL_VERIFYHOST => $verifyTls ? 2 : 0,
                CURLOPT_TIMEOUT => 20,
            ]);
            $body = curl_exec($ch);
            $err = curl_error($ch);
            $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($body === false) {
                return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $err ?: 'curl_exec_failed'];
            }
            $ok = self::looksOk((string)$body);
            if ($ok) {
                return ['ok' => true, 'status' => $status, 'body' => (string)$body];
            }
            $detail = self::extractError((string)$body) ?? self::summarizeBody((string)$body);
            return [
                'ok' => false,
                'status' => $status,
                'body' => (string)$body,
                'error' => $detail ? ('plesk_error: ' . $detail) : 'plesk_error',
            ];
        }

        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: text/xml\r\n" . "X-API-Key: {$apiKey}\r\n" . "KEY: {$apiKey}\r\n",
                'content' => $xml,
                'timeout' => 20,
            ],
            'ssl' => [
                'verify_peer' => $verifyTls,
                'verify_peer_name' => $verifyTls,
            ]
        ];
        $ctx = stream_context_create($opts);
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'http_request_failed'];
        }
        $ok = self::looksOk((string)$body);
        if ($ok) {
            return ['ok' => true, 'status' => 200, 'body' => (string)$body];
        }
        $detail = self::extractError((string)$body) ?? self::summarizeBody((string)$body);
        return [
            'ok' => false,
            'status' => 200,
            'body' => (string)$body,
            'error' => $detail ? ('plesk_error: ' . $detail) : 'plesk_error',
        ];
    }

    private static function looksOk(string $body): bool
    {
        // Very lightweight checks: status ok and/or result ok
        if (stripos($body, '<status>ok</status>') !== false) return true;
        if (stripos($body, '<status>error</status>') !== false) return false;
        if (stripos($body, 'result') !== false && stripos($body, 'ok') !== false && stripos($body, '<errtext>') === false) return true;
        return false;
    }

    private static function extractError(string $body): ?string
    {
        if ($body === '') return null;

        // Fast regex fallback (works even if XML parsing fails).
        $errtext = null;
        $errcode = null;
        if (preg_match('~<errtext>\s*(.*?)\s*</errtext>~is', $body, $m)) {
            $errtext = trim(html_entity_decode((string)$m[1], ENT_QUOTES | ENT_XML1));
        }
        if (preg_match('~<errcode>\s*(.*?)\s*</errcode>~is', $body, $m)) {
            $errcode = trim((string)$m[1]);
        }
        if (($errtext !== null && $errtext !== '') || ($errcode !== null && $errcode !== '')) {
            if ($errtext !== null && $errtext !== '' && $errcode !== null && $errcode !== '') {
                return $errtext . ' (code ' . $errcode . ')';
            }
            return ($errtext !== null && $errtext !== '') ? $errtext : ('code ' . (string)$errcode);
        }

        // Try to parse Plesk XML response and extract errcode/errtext.
        $prev = libxml_use_internal_errors(true);
        try {
            $xml = simplexml_load_string($body);
            if ($xml === false) {
                return null;
            }

            $errtext = null;
            $errcode = null;

            $texts = $xml->xpath('//errtext');
            if (is_array($texts) && isset($texts[0])) {
                $errtext = trim((string)$texts[0]);
            }

            $codes = $xml->xpath('//errcode');
            if (is_array($codes) && isset($codes[0])) {
                $errcode = trim((string)$codes[0]);
            }

            if ($errtext === '' && $errcode === '') {
                return null;
            }

            if ($errtext !== '' && $errcode !== '') {
                return $errtext . ' (code ' . $errcode . ')';
            }
            return $errtext !== '' ? $errtext : ('code ' . $errcode);
        } catch (\Throwable $e) {
            return null;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
        }
    }

    private static function summarizeBody(string $body): ?string
    {
        $body = trim($body);
        if ($body === '') return null;

        // If it's HTML (e.g., error page), strip tags to get a hint.
        $text = strip_tags($body);
        $text = preg_replace('~\s+~', ' ', $text);
        $text = trim((string)$text);
        if ($text === '') return null;

        // Keep it short to avoid dumping too much into DB/UI.
        $max = 180;
        if (strlen($text) > $max) {
            $text = substr($text, 0, $max) . '…';
        }
        return $text;
    }
}
