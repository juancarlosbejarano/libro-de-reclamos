<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Views\View;

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
}
