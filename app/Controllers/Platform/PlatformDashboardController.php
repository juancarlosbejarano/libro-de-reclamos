<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Models\SystemKV;
use App\Views\View;

final class PlatformDashboardController
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
        $tenants = (int)$pdo->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        $complaints = (int)$pdo->query('SELECT COUNT(*) FROM complaints')->fetchColumn();
        $open = (int)$pdo->query('SELECT COUNT(*) FROM complaints WHERE status="open"')->fetchColumn();
        $inProgress = (int)$pdo->query('SELECT COUNT(*) FROM complaints WHERE status="in_progress"')->fetchColumn();
        $closed = (int)$pdo->query('SELECT COUNT(*) FROM complaints WHERE status="closed"')->fetchColumn();
        $jobsPending = (int)$pdo->query('SELECT COUNT(*) FROM domain_provisioning_jobs WHERE status="pending"')->fetchColumn();
        $jobsFailed = (int)$pdo->query('SELECT COUNT(*) FROM domain_provisioning_jobs WHERE status="failed"')->fetchColumn();

        $cron = SystemKV::get('plesk_provision_last_run');

        return Response::html(View::render('platform/dashboard', [
            'stats' => [
                'tenants' => $tenants,
                'complaints' => $complaints,
                'open' => $open,
                'in_progress' => $inProgress,
                'closed' => $closed,
                'jobs_pending' => $jobsPending,
                'jobs_failed' => $jobsFailed,
            ],
            'cron' => $cron,
        ]));
    }
}
