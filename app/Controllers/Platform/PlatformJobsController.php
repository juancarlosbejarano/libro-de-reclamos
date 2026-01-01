<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\DB;
use App\Models\SystemKV;
use App\Support\Env;
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
        $rows = $pdo->query('SELECT id, tenant_id, domain, action, status, attempts, last_error, created_at, processed_at FROM domain_provisioning_jobs ORDER BY id DESC LIMIT 200')->fetchAll();
        $jobs = is_array($rows) ? $rows : [];

        $statsRows = $pdo->query('SELECT status, COUNT(*) AS c FROM domain_provisioning_jobs GROUP BY status')->fetchAll();
        $stats = ['pending' => 0, 'failed' => 0, 'success' => 0];
        if (is_array($statsRows)) {
            foreach ($statsRows as $r) {
                if (!is_array($r)) continue;
                $s = (string)($r['status'] ?? '');
                $c = (int)($r['c'] ?? 0);
                if ($s !== '' && array_key_exists($s, $stats)) {
                    $stats[$s] = $c;
                }
            }
        }

        $cron = SystemKV::get('plesk_provision_last_run');

        $apiUrl = (string)(Env::get('PLESK_API_URL', '') ?? '');
        $apiUrlShort = '';
        if ($apiUrl !== '') {
            $p = parse_url($apiUrl);
            if (is_array($p)) {
                $scheme = (string)($p['scheme'] ?? '');
                $host = (string)($p['host'] ?? '');
                $port = isset($p['port']) ? ':' . (string)$p['port'] : '';
                $path = (string)($p['path'] ?? '');
                $apiUrlShort = ($scheme && $host) ? ($scheme . '://' . $host . $port . $path) : $apiUrl;
            } else {
                $apiUrlShort = $apiUrl;
            }
        }

        $plesk = [
            'auto' => Env::bool('PLESK_AUTO_PROVISION', true),
            'site' => (string)(Env::get('PLESK_SITE_NAME', '') ?? ''),
            'url' => $apiUrlShort,
            'tls' => Env::bool('PLESK_VERIFY_TLS', true),
            'has_key' => ((string)(Env::get('PLESK_API_KEY', '') ?? '')) !== '',
        ];

        return Response::html(View::render('platform/jobs', [
            'jobs' => $jobs,
            'stats' => $stats,
            'cron' => $cron,
            'plesk' => $plesk,
        ]));
    }
}
