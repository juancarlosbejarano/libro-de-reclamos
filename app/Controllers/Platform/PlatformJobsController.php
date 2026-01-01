<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Views\View;

final class PlatformJobsController
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
        $rows = $pdo->query('SELECT id, tenant_id, domain, status, attempts, last_error, created_at, processed_at FROM domain_provisioning_jobs ORDER BY id DESC LIMIT 200')->fetchAll();
        $jobs = is_array($rows) ? $rows : [];
        return Response::html(View::render('platform/jobs', ['jobs' => $jobs]));
    }
}
