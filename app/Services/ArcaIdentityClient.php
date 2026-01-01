<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SystemKV;
use App\Support\Crypto;
use App\Support\Env;

final class ArcaIdentityClient
{
    /** @return array{ok:bool,status:int,error?:string,json?:array<string,mixed>} */
    private static function getJson(string $url, bool $insecureSsl = false): array
    {
        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'error' => 'curl_required'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'error' => 'curl_init_failed'];
        }

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ];

        if ($insecureSsl) {
            // WARNING: insecure; use only when the hosting is missing CA bundles.
            $opts[CURLOPT_SSL_VERIFYPEER] = false;
            $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        }

        curl_setopt_array($ch, $opts);

        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'error' => $err ?: 'curl_exec_failed'];
        }

        $decoded = json_decode((string)$body, true);
        $ok = $status >= 200 && $status < 300 && is_array($decoded);
        return $ok
            ? ['ok' => true, 'status' => $status, 'json' => $decoded]
            : ['ok' => false, 'status' => $status, 'error' => 'http_' . $status, 'json' => is_array($decoded) ? $decoded : null];
    }

    private static function token(): string
    {
        $row = SystemKV::get('arca_api_token_enc');
        if (!$row) {
            throw new \RuntimeException('arca_token_missing');
        }
        $packed = (string)($row['v'] ?? '');
        if ($packed === '') {
            throw new \RuntimeException('arca_token_missing');
        }
        return Crypto::decrypt($packed);
    }

    /**
     * @param 'ruc'|'dni' $kind
     * @return array{ok:bool,name?:string,error?:string,raw?:array<string,mixed>}
     */
    public static function lookup(string $kind, string $number): array
    {
        $kind = strtolower(trim($kind));
        if (!in_array($kind, ['ruc', 'dni'], true)) {
            return ['ok' => false, 'error' => 'invalid_kind'];
        }

        $digits = preg_replace('/\D+/', '', $number) ?? '';
        if ($kind === 'ruc' && strlen($digits) !== 11) {
            return ['ok' => false, 'error' => 'invalid_ruc'];
        }
        if ($kind === 'dni' && strlen($digits) !== 8) {
            return ['ok' => false, 'error' => 'invalid_dni'];
        }

        $token = self::token();
        $url = 'https://api.arca.digital/api/' . $kind . '/' . rawurlencode($digits) . '?api_token=' . rawurlencode($token);

        $allowInsecure = Env::bool('ARCA_API_INSECURE_SSL', false);

        $res = self::getJson($url, $allowInsecure);
        if (!$res['ok'] && !$allowInsecure) {
            $err = (string)($res['error'] ?? '');
            // Some hostings fail TLS validation due to missing CA bundle.
            if (stripos($err, 'SSL') !== false || stripos($err, 'certificate') !== false) {
                $res = self::getJson($url, true);
            }
        }
        if (!$res['ok']) {
            return ['ok' => false, 'error' => $res['error'] ?? 'lookup_failed', 'raw' => $res['json'] ?? null];
        }

        $json = $res['json'] ?? [];
        $data = $json;
        if (isset($json['data']) && is_array($json['data'])) {
            $data = $json['data'];
        }

        $name = self::extractName($kind, $data);
        if ($name === null && isset($json['result']) && is_array($json['result'])) {
            $name = self::extractName($kind, $json['result']);
        }

        if ($name === null) {
            return ['ok' => false, 'error' => 'unexpected_response', 'raw' => $json];
        }

        return ['ok' => true, 'name' => $name, 'raw' => $json];
    }

    /** @param array<string,mixed> $data */
    private static function extractName(string $kind, array $data): ?string
    {
        $candidates = [];

        if ($kind === 'ruc') {
            $candidates = [
                $data['razon_social'] ?? null,
                $data['nombre_o_razon_social'] ?? null,
                $data['nombre'] ?? null,
                $data['business_name'] ?? null,
            ];
        } else {
            $candidates = [
                $data['nombre_completo'] ?? null,
                $data['nombre'] ?? null,
                $data['full_name'] ?? null,
            ];

            $nombres = $data['nombres'] ?? null;
            $ap = $data['apellido_paterno'] ?? null;
            $am = $data['apellido_materno'] ?? null;
            if (is_string($nombres) || is_string($ap) || is_string($am)) {
                $parts = [];
                if (is_string($nombres) && trim($nombres) !== '') $parts[] = trim($nombres);
                if (is_string($ap) && trim($ap) !== '') $parts[] = trim($ap);
                if (is_string($am) && trim($am) !== '') $parts[] = trim($am);
                if ($parts) {
                    $candidates[] = implode(' ', $parts);
                }
            }
        }

        foreach ($candidates as $c) {
            if (is_string($c)) {
                $c = trim($c);
                if ($c !== '') return $c;
            }
        }
        return null;
    }
}
