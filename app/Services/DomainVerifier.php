<?php
declare(strict_types=1);

namespace App\Services;

use App\Support\Env;

final class DomainVerifier
{
    private const DEFAULT_PLATFORM_IP = '207.58.173.84';

    /**
     * Verifies a custom domain is pointing to this platform.
     * Accepts either:
     * - CNAME -> {tenantSlug}.{PLATFORM_BASE_DOMAIN} (preferred) or -> PLATFORM_BASE_DOMAIN
     * - A record -> one of PLATFORM_ALLOWED_IPS OR one of the A records of PLATFORM_BASE_DOMAIN
     *
     * @return array{ok:bool, reason:string, details?:array<string,mixed>}
     */
    public static function verifyCustomDomain(string $rawDomain, string $tenantSlug): array
    {
        $domain = self::normalizeDomain($rawDomain);
        if ($domain === '') {
            return ['ok' => false, 'reason' => 'domain_required'];
        }
        if (!preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/', $domain)) {
            return ['ok' => false, 'reason' => 'domain_invalid'];
        }

        $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
        if ($base === '') {
            return ['ok' => false, 'reason' => 'platform_base_domain_missing'];
        }

        // Custom domains should not be inside platform base domain
        if ($domain === $base || str_ends_with($domain, '.' . $base)) {
            return ['ok' => false, 'reason' => 'domain_is_platform_domain'];
        }

        $allowedCnames = [
            strtolower($tenantSlug . '.' . $base),
            strtolower($base),
        ];

        // 1) CNAME check
        $cname = self::getCnameTarget($domain);
        if ($cname !== null) {
            $cnameNorm = strtolower(rtrim($cname, '.'));
            if (in_array($cnameNorm, $allowedCnames, true)) {
                return ['ok' => true, 'reason' => 'cname_ok', 'details' => ['cname' => $cnameNorm]];
            }
            // Do not fail immediately: some DNS setups may expose a CNAME that doesn't match,
            // while the A record still resolves to the correct hosting IP.
            // Fall back to A record validation.
        }

        // 2) A record check
        $domainIps = self::getARecords($domain);
        if (!$domainIps) {
            return ['ok' => false, 'reason' => 'no_dns_records'];
        }

        $allowedIps = self::allowedPlatformIps($base);
        if (!$allowedIps) {
            return ['ok' => false, 'reason' => 'platform_ips_unknown', 'details' => ['domain_ips' => $domainIps]];
        }

        foreach ($domainIps as $ip) {
            if (in_array($ip, $allowedIps, true)) {
                return ['ok' => true, 'reason' => 'a_ok', 'details' => ['ip' => $ip]];
            }
        }
        return ['ok' => false, 'reason' => 'a_mismatch', 'details' => ['domain_ips' => $domainIps, 'expected_ips' => $allowedIps]];
    }

    public static function normalizeDomain(string $raw): string
    {
        $raw = trim(strtolower($raw));
        $raw = preg_replace('#^https?://#', '', $raw) ?? $raw;
        $raw = explode('/', $raw, 2)[0];
        $raw = explode(':', $raw, 2)[0];
        return rtrim($raw, '.');
    }

    /** @return list<string> */
    private static function getARecords(string $domain): array
    {
        $ips = [];
        if (function_exists('dns_get_record')) {
            $records = @dns_get_record($domain, DNS_A);
            if (is_array($records)) {
                foreach ($records as $r) {
                    if (is_array($r) && isset($r['ip']) && is_string($r['ip'])) {
                        $ips[] = $r['ip'];
                    }
                }
            }
        }
        $ips = array_values(array_unique(array_filter($ips, fn($v) => is_string($v) && $v !== '')));
        if ($ips) return $ips;

        $ip = @gethostbyname($domain);
        if (is_string($ip) && $ip !== '' && $ip !== $domain) {
            return [$ip];
        }
        return [];
    }

    private static function getCnameTarget(string $domain): ?string
    {
        if (!function_exists('dns_get_record')) {
            return null;
        }
        $records = @dns_get_record($domain, DNS_CNAME);
        if (!is_array($records) || !$records) {
            return null;
        }
        foreach ($records as $r) {
            if (is_array($r) && isset($r['target']) && is_string($r['target']) && $r['target'] !== '') {
                return $r['target'];
            }
        }
        return null;
    }

    /** @return list<string> */
    private static function allowedPlatformIps(string $baseDomain): array
    {
        $raw = trim((string)(Env::get('PLATFORM_ALLOWED_IPS', '') ?? ''));
        if ($raw !== '') {
            $ips = array_map('trim', explode(',', $raw));
            return array_values(array_unique(array_filter($ips, fn($v) => is_string($v) && $v !== '')));
        }
        $ips = self::getARecords($baseDomain);
        // If PLATFORM_ALLOWED_IPS is not set, allow the known platform IP used in the UI instructions.
        $ips[] = self::DEFAULT_PLATFORM_IP;
        return array_values(array_unique(array_filter($ips, fn($v) => is_string($v) && $v !== '')));
    }
}
