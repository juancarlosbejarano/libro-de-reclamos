<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\TenantDomain;
use App\Models\DomainProvisioningJob;
use App\Services\DomainVerifier;
use App\Services\TenantResolver;
use App\Support\Csrf;
use App\Support\Env;
use App\Views\View;

final class SettingsController
{
    public function domain(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return Response::redirect('/login');
        }
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        $tenantId = TenantResolver::tenantId();
        $domains = TenantDomain::listForTenant($tenantId);
        return Response::html(View::render('settings/domain', ['domains' => $domains]));
    }

    public function saveDomain(Request $request): Response
    {
        $user = $_SESSION['user'] ?? null;
        if (!$user) {
            return Response::redirect('/login');
        }
        if (($user['role'] ?? '') !== 'admin') {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('settings/domain', ['error' => 'CSRF inválido', 'domains' => []]), 400);
        }

        $domain = strtolower(trim((string)($request->post['domain'] ?? '')));
        $makePrimary = isset($request->post['is_primary']) && (string)$request->post['is_primary'] === '1';

        if ($domain === '') {
            return Response::html(View::render('settings/domain', ['error' => 'Dominio requerido', 'domains' => []]), 422);
        }
        $tenantId = TenantResolver::tenantId();
        $tenantSlug = (string)($_SESSION['_tenant_slug'] ?? '');

        $requiresVerify = Env::bool('DOMAIN_VERIFY_REQUIRED', true);
        $verified = false;
        if ($requiresVerify) {
            $vr = DomainVerifier::verifyCustomDomain($domain, $tenantSlug);
            if (!$vr['ok']) {
                $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
                $cnameTo = ($base !== '' && $tenantSlug !== '') ? ($tenantSlug . '.' . $base) : '';
                $hint = ' Debe apuntar por DNS (A) a 207.58.173.84';
                if ($cnameTo !== '') {
                    $hint .= ' o ser CNAME a ' . $cnameTo;
                }
                $hint .= '.';
                $domains = TenantDomain::listForTenant($tenantId);
                return Response::html(View::render('settings/domain', [
                    'error' => 'Dominio no verificado (' . $vr['reason'] . ').' . $hint,
                    'domains' => $domains,
                ]), 422);
            }
            $verified = true;
        }
        try {
            TenantDomain::addCustom($tenantId, DomainVerifier::normalizeDomain($domain), $makePrimary, $verified);
            if ($verified && Env::bool('PLESK_AUTO_PROVISION', true)) {
                DomainProvisioningJob::enqueueAliasCreate($tenantId, DomainVerifier::normalizeDomain($domain));
            }
        } catch (\Throwable $e) {
            // Likely duplicate domain
            $domains = TenantDomain::listForTenant($tenantId);
            return Response::html(View::render('settings/domain', ['error' => 'No se pudo agregar dominio (¿ya existe?): ' . $e->getMessage(), 'domains' => $domains]), 409);
        }

        return Response::redirect('/settings/domain');
    }
}

