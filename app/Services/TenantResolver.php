<?php
declare(strict_types=1);

namespace App\Services;

use App\Models\Tenant;
use App\Support\Env;

final class TenantResolver
{
    public static function resolveFromHost(string $host): void
    {
        $tenant = Tenant::findByHost($host);

        // Optional: resolve by subdomain under platform base domain (e.g. {slug}.ldr.arca.digital)
        if (!$tenant && Env::bool('ALLOW_SUBDOMAIN_TENANTS', true)) {
            $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
            if ($base !== '' && $host !== '' && $host !== $base && str_ends_with($host, '.' . $base)) {
                $sub = substr($host, 0, -1 * (strlen($base) + 1));
                // only allow a single-label subdomain (slug)
                if ($sub !== '' && !str_contains($sub, '.')) {
                    $candidate = Tenant::findBySlug($sub);
                    if ($candidate) {
                        $tenant = $candidate;
                    }
                }
            }
        }

        if (!$tenant) {
            $defaultSlug = Env::get('DEFAULT_TENANT_SLUG', 'platform') ?? 'platform';
            $tenant = Tenant::findBySlug($defaultSlug);
        }
        if (!$tenant) {
            // Still no tenant: keep a safe default for error messaging.
            $_SESSION['_tenant_id'] = 0;
            $_SESSION['_tenant_slug'] = 'unknown';
            return;
        }
        $_SESSION['_tenant_id'] = (int)$tenant['id'];
        $_SESSION['_tenant_slug'] = (string)$tenant['slug'];
    }

    public static function tenantId(): int
    {
        return (int)($_SESSION['_tenant_id'] ?? 0);
    }
}

