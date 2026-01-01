<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\SystemKV;
use App\Support\Crypto;
use App\Support\Env;

final class ArcaIdentityClient
{
    /**
     * @param array<string,mixed> $data
     * @param string[] $keys
     */
    private static function findFirstStringByKeys(array $data, array $keys, int $depth = 0): ?string
    {
        if ($depth > 4) return null;

        foreach ($keys as $k) {
            if (!array_key_exists($k, $data)) continue;
            $v = $data[$k];
            if (is_string($v)) {
                $v = trim($v);
                if ($v !== '') return $v;
            }
            if (is_array($v)) {
                $found = self::findFirstStringByKeys($v, $keys, $depth + 1);
                if ($found !== null) return $found;
            }
        }

        // Fallback: scan nested arrays
        foreach ($data as $v) {
            if (is_array($v)) {
                $found = self::findFirstStringByKeys($v, $keys, $depth + 1);
                if ($found !== null) return $found;
            }
        }

        return null;
    }

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
        // Prefer plaintext storage to avoid crypto/key issues in some hostings.
        $plain = SystemKV::get('arca_api_token');
        if ($plain) {
            $token = trim((string)($plain['v'] ?? ''));
            if ($token !== '') return $token;
        }

        // Backwards-compatible fallback: legacy encrypted storage.
        $legacy = SystemKV::get('arca_api_token_enc');
        if (!$legacy) {
            throw new \RuntimeException('arca_token_missing');
        }
        $packed = (string)($legacy['v'] ?? '');
        if (trim($packed) === '') {
            throw new \RuntimeException('arca_token_missing');
        }

        return Crypto::decrypt($packed);
    }

    /**
     * @param 'ruc'|'dni' $kind
        * @return array{ok:bool,name?:string,address_full?:string,error?:string,raw?:array<string,mixed>}
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

        // Some APIs return 200 with an error payload.
        if (isset($json['success']) && $json['success'] === false) {
            $msg = is_string($json['message'] ?? null) ? (string)$json['message'] : 'api_error';
            return ['ok' => false, 'error' => $msg, 'raw' => $json];
        }
        if (isset($json['ok']) && $json['ok'] === false) {
            $msg = is_string($json['message'] ?? null) ? (string)$json['message'] : 'api_error';
            return ['ok' => false, 'error' => $msg, 'raw' => $json];
        }
        if (isset($json['error']) && is_string($json['error']) && trim($json['error']) !== '') {
            return ['ok' => false, 'error' => (string)$json['error'], 'raw' => $json];
        }

        $data = $json;
        if (isset($json['data']) && is_array($json['data'])) {
            $data = $json['data'];
        }
        // If data is a list, use the first element.
        if (is_array($data) && isset($data[0]) && is_array($data[0])) {
            $data = $data[0];
        }

        $name = self::extractName($kind, $data);
        if ($name === null && isset($json['result']) && is_array($json['result'])) {
            $name = self::extractName($kind, $json['result']);
        }

        $addressFull = null;
        if ($kind === 'ruc') {
            $addressFull = self::extractAddressFull($data);
            if ($addressFull === null && isset($json['result']) && is_array($json['result'])) {
                $addressFull = self::extractAddressFull($json['result']);
            }
        }

        if ($name === null) {
            return ['ok' => false, 'error' => 'unexpected_response', 'raw' => $json];
        }

        $out = ['ok' => true, 'name' => $name, 'raw' => $json];
        if (is_string($addressFull) && trim($addressFull) !== '') {
            $out['address_full'] = $addressFull;
        }
        return $out;
    }

    /** @param array<string,mixed> $data */
    private static function extractAddressFull(array $data): ?string
    {
        $keys = [
            'direccion_completa',
            'direccionCompleta',
            'direccion',
            'address_full',
            'addressFull',
            'address',
        ];
        return self::findFirstStringByKeys($data, $keys);
    }

    /** @param array<string,mixed> $data */
    private static function extractName(string $kind, array $data): ?string
    {
        if ($kind === 'ruc') {
            $keys = [
                'razon_social',
                'razonSocial',
                'razonSocialSunat',
                'nombre_o_razon_social',
                'nombreORazonSocial',
                'nombre_razon_social',
                'nombre',
                'name',
                'business_name',
                'businessName',
                'denominacion',
                'empresa',
                'company',
                'rznSocial',
            ];
            return self::findFirstStringByKeys($data, $keys);
        }

        $keys = [
            'nombre_completo',
            'nombreCompleto',
            'full_name',
            'fullName',
            'nombre',
            'name',
        ];
        $direct = self::findFirstStringByKeys($data, $keys);
        if ($direct !== null) return $direct;

        $nombres = $data['nombres'] ?? ($data['first_names'] ?? null);
        $ap = $data['apellido_paterno'] ?? ($data['apellidoPaterno'] ?? null);
        $am = $data['apellido_materno'] ?? ($data['apellidoMaterno'] ?? null);
        if (is_string($nombres) || is_string($ap) || is_string($am)) {
            $parts = [];
            if (is_string($nombres) && trim($nombres) !== '') $parts[] = trim($nombres);
            if (is_string($ap) && trim($ap) !== '') $parts[] = trim($ap);
            if (is_string($am) && trim($am) !== '') $parts[] = trim($am);
            if ($parts) return implode(' ', $parts);
        }

        return null;
    }
}
