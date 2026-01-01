<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Models\Tenant;
use App\Views\View;
use App\Support\Csrf;
use App\Support\Env;
use App\Services\ArcaIdentityClient;

final class PlatformTenantsController
{
    private function requirePlatformUser(Request $request): ?Response
    {
        $u = $_SESSION['platform_user'] ?? null;
        if (!$u) return Response::redirect('/platform/login');
        return null;
    }

    public function index(Request $request): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        $pdo = DB::pdo();
        $sql = 'SELECT t.id, t.slug, t.name, t.created_at,
                       (SELECT COUNT(*) FROM complaints c WHERE c.tenant_id = t.id) AS complaints_count,
                       (SELECT COUNT(*) FROM tenant_domains d WHERE d.tenant_id = t.id) AS domains_count,
                       (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.role = "admin") AS admins_count
                FROM tenants t
                ORDER BY t.id DESC
                LIMIT 200';
        $rows = $pdo->query($sql)->fetchAll();
        $tenants = is_array($rows) ? $rows : [];

        return Response::html(View::render('platform/tenants', ['tenants' => $tenants]));
    }

    public function create(Request $request): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        return Response::html(View::render('platform/tenant_create', ['form' => ['id_type' => 'ruc']]));
    }

    public function store(Request $request): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('platform/tenant_create', ['error' => 'CSRF inválido']), 400);
        }

        $mode = (string)($request->post['mode'] ?? '');
        $idType = strtolower(trim((string)($request->post['id_type'] ?? 'ruc')));
        $idNumberRaw = (string)($request->post['id_number'] ?? '');
        $idNumber = preg_replace('/\D+/', '', $idNumberRaw) ?? '';
        $name = trim((string)($request->post['name'] ?? ''));
        $slug = strtolower(trim((string)($request->post['slug'] ?? '')));

        $form = [
            'id_type' => $idType,
            'id_number' => $idNumber,
            'name' => $name,
            'slug' => $slug,
        ];

        if ($mode === 'lookup') {
            try {
                $res = ArcaIdentityClient::lookup($idType === 'dni' ? 'dni' : 'ruc', $idNumber);
                if (!$res['ok']) {
                    return Response::html(View::render('platform/tenant_create', [
                        'error' => 'No se pudo consultar: ' . ($res['error'] ?? 'error'),
                        'form' => $form,
                    ]), 422);
                }
                $form['name'] = (string)($res['name'] ?? '');
                return Response::html(View::render('platform/tenant_create', ['form' => $form]));
            } catch (\Throwable $e) {
                return Response::html(View::render('platform/tenant_create', [
                    'error' => 'No se pudo consultar',
                    'form' => $form,
                ]), 500);
            }
        }

        if ($name === '' || $slug === '') {
            return Response::html(View::render('platform/tenant_create', ['error' => 'Completa los campos requeridos', 'form' => $form]), 422);
        }

        // slug: only [a-z0-9-], 3..32
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $slug)) {
            return Response::html(View::render('platform/tenant_create', ['error' => 'Subdominio inválido (usa letras/números/guiones)', 'form' => $form]), 422);
        }

        if (Tenant::slugExists($slug)) {
            return Response::html(View::render('platform/tenant_create', ['error' => 'Ese subdominio ya existe', 'form' => $form]), 409);
        }

        $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
        if ($base === '') {
            return Response::html(View::render('platform/tenant_create', ['error' => 'Falta PLATFORM_BASE_DOMAIN en .env', 'form' => $form]), 500);
        }

        $subdomain = $slug . '.' . $base;

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            $tenantId = Tenant::create($slug, $name, in_array($idType, ['ruc', 'dni'], true) ? $idType : null, $idNumber !== '' ? $idNumber : null);

            $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at) VALUES (:tid, :domain, :kind, 1, NOW(), NOW())');
            $stmt->execute(['tid' => $tenantId, 'domain' => $subdomain, 'kind' => 'subdomain']);

            $pdo->commit();
            return Response::redirect('/platform/tenants');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::html(View::render('platform/tenant_create', ['error' => 'No se pudo crear: ' . $e->getMessage(), 'form' => $form]), 500);
        }
    }
}
