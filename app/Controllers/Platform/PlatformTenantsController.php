<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Models\Tenant;
use App\Models\TenantDomain;
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
        $sql = 'SELECT t.id, t.slug, t.name, t.status, t.created_at,
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
        $addressFull = trim((string)($request->post['address_full'] ?? ''));
        $slugRaw = strtolower(trim((string)($request->post['slug'] ?? '')));
        $slug = $slugRaw;

        // Users sometimes paste a full domain here (e.g. "ad.jbsistemas.com").
        // This field expects only the subdomain label (e.g. "ad").
        if ($slug !== '' && str_contains($slug, '.')) {
            $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));

            // If they pasted "slug.baseDomain", strip the base.
            if ($base !== '' && str_ends_with($slug, '.' . $base)) {
                $candidate = substr($slug, 0, -1 * (strlen($base) + 1));
                if (is_string($candidate)) {
                    $slug = trim($candidate);
                }
            } else {
                // Otherwise, take the first label.
                $parts = explode('.', $slug);
                $slug = trim((string)($parts[0] ?? ''));
            }
        }

        $form = [
            'id_type' => $idType,
            'id_number' => $idNumber,
            'name' => $name,
            'address_full' => $addressFull,
            'slug' => $slug,
        ];

        if ($mode === 'lookup') {
            try {
                $res = ArcaIdentityClient::lookup($idType === 'dni' ? 'dni' : 'ruc', $idNumber);
                if (!$res['ok']) {
                    $detail = '';
                    if (($res['error'] ?? null) === 'unexpected_response' && isset($res['raw']) && is_array($res['raw'])) {
                        $keys = array_keys($res['raw']);
                        $keys = array_slice($keys, 0, 12);
                        $detail = ' (keys: ' . implode(', ', array_map('strval', $keys)) . ')';
                    }
                    return Response::html(View::render('platform/tenant_create', [
                        'error' => 'No se pudo consultar: ' . ($res['error'] ?? 'error') . $detail,
                        'form' => $form,
                    ]), 422);
                }
                $form['name'] = (string)($res['name'] ?? '');
                if (isset($res['address_full']) && is_string($res['address_full'])) {
                    $form['address_full'] = $res['address_full'];
                }
                return Response::html(View::render('platform/tenant_create', ['form' => $form]));
            } catch (\Throwable $e) {
                return Response::html(View::render('platform/tenant_create', [
                    'error' => 'No se pudo consultar: ' . $e->getMessage(),
                    'form' => $form,
                ]), 500);
            }
        }

        if ($name === '' || $slug === '') {
            return Response::html(View::render('platform/tenant_create', ['error' => 'Completa los campos requeridos', 'form' => $form]), 422);
        }

        // slug: only [a-z0-9-], 3..32
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $slug)) {
            $msg = 'Subdominio inválido. Usa solo letras/números/guiones (ej: miempresa)';
            if ($slugRaw !== '' && str_contains($slugRaw, '.')) {
                $msg = 'Subdominio inválido. Ingresa solo la etiqueta (ej: "ad"), no el dominio completo.';
            }
            return Response::html(View::render('platform/tenant_create', ['error' => $msg, 'form' => $form]), 422);
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
            $tenantId = Tenant::create(
                $slug,
                $name,
                in_array($idType, ['ruc', 'dni'], true) ? $idType : null,
                $idNumber !== '' ? $idNumber : null,
                $addressFull !== '' ? $addressFull : null
            );

            $stmt = $pdo->prepare('INSERT INTO tenant_domains (tenant_id, domain, kind, is_primary, verified_at, created_at) VALUES (:tid, :domain, :kind, 1, NOW(), NOW())');
            $stmt->execute(['tid' => $tenantId, 'domain' => $subdomain, 'kind' => 'subdomain']);

            $pdo->commit();
            return Response::redirect('/platform/tenants');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::html(View::render('platform/tenant_create', ['error' => 'No se pudo crear: ' . $e->getMessage(), 'form' => $form]), 500);
        }
    }

    public function edit(Request $request, array $params): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        $id = (int)($params['id'] ?? 0);
        $tenant = $id > 0 ? Tenant::findById($id) : null;
        if (!$tenant) {
            return Response::html(View::render('platform/tenant_edit', ['tenant' => null]), 404);
        }

        return Response::html(View::render('platform/tenant_edit', [
            'tenant' => $tenant,
            'saved' => $request->query['saved'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function update(Request $request, array $params): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        $id = (int)($params['id'] ?? 0);
        $tenant = $id > 0 ? Tenant::findById($id) : null;
        if (!$tenant) return Response::html(View::render('platform/tenant_edit', ['tenant' => null]), 404);

        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/platform/tenants/' . $id . '/edit?error=csrf');
        }

        $name = trim((string)($request->post['name'] ?? ''));
        $addressFull = trim((string)($request->post['address_full'] ?? ''));
        $slugRaw = strtolower(trim((string)($request->post['slug'] ?? (string)($tenant['slug'] ?? ''))));
        $slug = $slugRaw;

        // Normalize if user pasted a domain.
        if ($slug !== '' && str_contains($slug, '.')) {
            $base = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
            if ($base !== '' && str_ends_with($slug, '.' . $base)) {
                $candidate = substr($slug, 0, -1 * (strlen($base) + 1));
                if (is_string($candidate)) {
                    $slug = trim($candidate);
                }
            } else {
                $parts = explode('.', $slug);
                $slug = trim((string)($parts[0] ?? ''));
            }
        }

        $tenantForm = $tenant;
        $tenantForm['slug'] = $slug;

        if ($name === '') {
            return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => 'Completa el nombre']), 422);
        }

        // Prevent changing the platform tenant slug.
        if (((string)($tenant['slug'] ?? '')) === 'platform' && $slug !== 'platform') {
            return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => 'No se puede cambiar el subdominio del tenant platform']), 422);
        }

        if ($slug === '') {
            return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => 'Completa el subdominio']), 422);
        }

        // slug: only [a-z0-9-], 3..32
        if (!preg_match('/^[a-z0-9][a-z0-9-]{1,30}[a-z0-9]$/', $slug)) {
            $msg = 'Subdominio inválido. Usa solo letras/números/guiones (ej: miempresa)';
            if ($slugRaw !== '' && str_contains($slugRaw, '.')) {
                $msg = 'Subdominio inválido. Ingresa solo la etiqueta (ej: "ad"), no el dominio completo.';
            }
            return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => $msg]), 422);
        }

        $logoPath = (string)($tenant['logo_path'] ?? '');
        $logo = $request->files['logo'] ?? null;
        if (is_array($logo) && isset($logo['tmp_name']) && is_uploaded_file((string)$logo['tmp_name'])) {
            $original = strtolower((string)($logo['name'] ?? 'logo'));
            $ext = pathinfo($original, PATHINFO_EXTENSION);
            $ext = is_string($ext) ? strtolower($ext) : '';
            if (!in_array($ext, ['png', 'jpg', 'jpeg', 'webp'], true)) {
                return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => 'Formato de logo inválido (usa PNG/JPG/WebP)']), 422);
            }
            if (!@getimagesize((string)$logo['tmp_name'])) {
                return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => 'El archivo no parece una imagen válida']), 422);
            }

            $base = realpath(__DIR__ . '/../../../') ?: (__DIR__ . '/../../../');
            $dir = $base . '/httpdocs/uploads/tenants/' . $id;
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            foreach (glob($dir . '/logo.*') ?: [] as $old) {
                @unlink($old);
            }
            $filename = 'logo.' . ($ext === 'jpeg' ? 'jpg' : $ext);
            $dest = $dir . '/' . $filename;
            @move_uploaded_file((string)$logo['tmp_name'], $dest);
            $logoPath = '/uploads/tenants/' . $id . '/' . $filename;
        }

        $pdo = DB::pdo();
        $pdo->beginTransaction();
        try {
            if (Tenant::slugExistsForOther($slug, $id)) {
                throw new \RuntimeException('Ese subdominio ya existe');
            }

            // Update slug + ensure/update subdomain domain record.
            if ($slug !== (string)($tenant['slug'] ?? '')) {
                $baseDomain = strtolower(trim((string)(Env::get('PLATFORM_BASE_DOMAIN', '') ?? '')));
                if ($baseDomain === '') {
                    throw new \RuntimeException('Falta PLATFORM_BASE_DOMAIN en .env');
                }
                $subdomain = $slug . '.' . $baseDomain;
                if (TenantDomain::domainExists($subdomain, $id)) {
                    throw new \RuntimeException('Ese dominio ya está en uso');
                }
                Tenant::updateSlug($id, $slug);
                TenantDomain::upsertSubdomain($id, $subdomain);
            }

            Tenant::updateDetails($id, $name, $addressFull !== '' ? $addressFull : null, $logoPath !== '' ? $logoPath : null);
            $pdo->commit();
            return Response::redirect('/platform/tenants/' . $id . '/edit?saved=1');
        } catch (\Throwable $e) {
            $pdo->rollBack();
            return Response::html(View::render('platform/tenant_edit', ['tenant' => $tenantForm, 'error' => $e->getMessage()]), 422);
        }
    }

    public function suspend(Request $request, array $params): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/platform/tenants');
        }
        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            Tenant::suspend($id);
        }
        return Response::redirect('/platform/tenants/' . $id . '/edit?saved=1');
    }

    public function reactivate(Request $request, array $params): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/platform/tenants');
        }
        $id = (int)($params['id'] ?? 0);
        if ($id > 0) {
            Tenant::reactivate($id);
        }
        return Response::redirect('/platform/tenants/' . $id . '/edit?saved=1');
    }
}
