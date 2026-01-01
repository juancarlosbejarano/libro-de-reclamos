<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Models\DomainProvisioningJob;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Services\DomainVerifier;
use App\Support\Csrf;
use App\Support\Env;
use App\Views\View;

final class RegisterController
{
    public function show(Request $request): Response
    {
        return Response::html(View::render('auth/register'));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('auth/register', ['error' => 'CSRF inválido']), 400);
        }

        $company = trim((string)($request->post['company'] ?? ''));
        $slug = strtolower(trim((string)($request->post['slug'] ?? '')));
        $email = trim((string)($request->post['email'] ?? ''));
        $password = (string)($request->post['password'] ?? '');
        $customDomain = trim((string)($request->post['custom_domain'] ?? ''));

        if ($company === '' || $slug === '' || $email === '' || $password === '') {
            return Response::html(View::render('auth/register', ['error' => 'Completa todos los campos']), 422);
        }

        // slug: only [a-z0-9-], 3..32
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $slug)) {
            return Response::html(View::render('auth/register', ['error' => 'Subdominio inválido (usa letras/números/guiones)']), 422);
        }

        $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
        if ($base === '') {
            return Response::html(View::render('auth/register', ['error' => 'Falta PLATFORM_BASE_DOMAIN en .env']), 500);
        }

        $subdomain = $slug . '.' . $base;

        if (Tenant::slugExists($slug)) {
            return Response::html(View::render('auth/register', ['error' => 'Ese subdominio ya existe']), 409);
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $tenantId = Tenant::create($slug, $company);

            $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at) VALUES (:tid, :domain, :kind, 1, NOW(), NOW())');
            $stmt->execute(['tid' => $tenantId, 'domain' => $subdomain, 'kind' => 'subdomain']);

            if ($customDomain !== '') {
                $requiresVerify = Env::bool('DOMAIN_VERIFY_REQUIRED', true);
                $verified = false;
                $normalized = DomainVerifier::normalizeDomain($customDomain);
                if ($requiresVerify) {
                    $vr = DomainVerifier::verifyCustomDomain($normalized, $slug);
                    if (!$vr['ok']) {
                        $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
                        $cnameTo = ($base !== '' && $slug !== '') ? ($slug . '.' . $base) : '';
                        $hint = ' Debe apuntar por DNS (A) a 207.58.173.84';
                        if ($cnameTo !== '') {
                            $hint .= ' o ser CNAME a ' . $cnameTo;
                        }
                        $hint .= '.';
                        throw new \RuntimeException('Dominio no verificado (' . $vr['reason'] . ').' . $hint);
                    }
                    $verified = true;
                }
                TenantDomain::addCustom($tenantId, $normalized, true, $verified);
                if ($verified && Env::bool('PLESK_AUTO_PROVISION', true)) {
                    DomainProvisioningJob::enqueueAliasCreate($tenantId, $normalized);
                }
            }

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare('INSERT INTO users (tenant_id, email, password_hash, role, created_at) VALUES (:tid, :email, :hash, :role, NOW())');
            $stmt->execute(['tid' => $tenantId, 'email' => $email, 'hash' => $hash, 'role' => 'admin']);
            $userId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Log the new admin in
            $_SESSION['user'] = ['id' => $userId, 'email' => $email, 'role' => 'admin'];
            // Force tenant context to the newly created one for this session
            $_SESSION['_tenant_id'] = $tenantId;
            $_SESSION['_tenant_slug'] = $slug;

            return Response::redirect('/');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::html(View::render('auth/register', ['error' => 'No se pudo registrar: ' . $e->getMessage()]), 500);
        }
    }
}

