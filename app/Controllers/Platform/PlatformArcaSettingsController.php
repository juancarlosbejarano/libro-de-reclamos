<?php
declare(strict_types=1);

namespace App\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Models\SystemKV;
use App\Support\Crypto;
use App\Support\Csrf;
use App\Views\View;

final class PlatformArcaSettingsController
{
    private function requirePlatformUser(Request $request): ?Response
    {
        $u = $_SESSION['platform_user'] ?? null;
        if (!$u) return Response::redirect('/platform/login');
        return null;
    }

    public function show(Request $request): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        $row = SystemKV::get('arca_api_token_enc');
        $configured = $row && ((string)($row['v'] ?? '')) !== '';

        return Response::html(View::render('platform/arca_settings', [
            'configured' => $configured,
            'saved' => $request->query['saved'] ?? null,
            'error' => $request->query['error'] ?? null,
        ]));
    }

    public function save(Request $request): Response
    {
        $guard = $this->requirePlatformUser($request);
        if ($guard) return $guard;

        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/platform/settings/arca?error=csrf');
        }

        $token = trim((string)($request->post['api_token'] ?? ''));
        if ($token === '') {
            return Response::redirect('/platform/settings/arca?error=required');
        }

        try {
            $enc = Crypto::encrypt($token);
            SystemKV::set('arca_api_token_enc', $enc);
            return Response::redirect('/platform/settings/arca?saved=1');
        } catch (\Throwable $e) {
            return Response::redirect('/platform/settings/arca?error=save_failed');
        }
    }
}
