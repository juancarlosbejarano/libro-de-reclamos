<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Views\View;

final class PlatformReportsController
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
        $stmt = $pdo->prepare('SELECT DATE(created_at) AS d, COUNT(*) AS c FROM complaints WHERE created_at >= (NOW() - INTERVAL 14 DAY) GROUP BY DATE(created_at) ORDER BY d ASC');
        $stmt->execute();
        $rows = $stmt->fetchAll();
        $byDay = is_array($rows) ? $rows : [];

        return Response::html(View::render('platform/reports', ['byDay' => $byDay]));
    }
}
