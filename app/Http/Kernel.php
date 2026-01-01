<?php
declare(strict_types=1);

namespace App\Http;

use App\Controllers\Api\ApiAuthController;
use App\Controllers\Api\ApiComplaintsController;
use App\Controllers\HomeController;
use App\Controllers\SessionController;
use App\Controllers\Platform\PlatformDashboardController;
use App\Controllers\Platform\PlatformJobsController;
use App\Controllers\Platform\PlatformReportsController;
use App\Controllers\Platform\PlatformArcaSettingsController;
use App\Controllers\Platform\PlatformSessionController;
use App\Controllers\Platform\PlatformTenantsController;
use App\Controllers\Ui\ComplaintsController;
use App\Controllers\Ui\RegisterController;
use App\Controllers\Ui\SettingsController;
use App\Controllers\Ui\SettingsMailController;
use App\Controllers\Ui\SettingsUsersController;
use App\Controllers\Ui\SettingsWhatsAppController;
use App\Services\I18n;
use App\Services\TenantResolver;
use App\Http\Response;
use App\Views\View;

final class Kernel
{
    private Router $router;

    public function __construct()
    {
        $this->router = new Router();
        $this->registerRoutes();
    }

    public function handle(Request $request): Response
    {
        // Resolve tenant early
        TenantResolver::resolveFromHost($request->host);
        I18n::bootstrap($request);

        // Enforce tenant suspension (do not block platform routes).
        if (TenantResolver::tenantStatus() === 'suspended') {
            $p = $request->path;
            if ($p !== '/health' && !str_starts_with($p, '/platform')) {
                return Response::html(View::render('errors/suspended'), 403);
            }
        }

        // PWA headers
        if ($request->path === '/' || str_starts_with($request->path, '/complaints')) {
            // no-op
        }

        $response = $this->router->dispatch($request);
        if ($response !== null) {
            return $response;
        }
        return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
    }

    private function registerRoutes(): void
    {
        // Health
        $this->router->add('GET', '#^/health$#', fn() => Response::json(['ok' => true]));

        // Platform (system owner/support)
        $pSession = new PlatformSessionController();
        $this->router->add('GET', '#^/platform/login$#', [$pSession, 'showLogin']);
        $this->router->add('POST', '#^/platform/login$#', [$pSession, 'login']);
        $this->router->add('POST', '#^/platform/logout$#', [$pSession, 'logout']);
        $this->router->add('GET', '#^/platform$#', [new PlatformDashboardController(), 'index']);
        $this->router->add('GET', '#^/platform/tenants$#', [new PlatformTenantsController(), 'index']);
        $this->router->add('GET', '#^/platform/tenants/create$#', [new PlatformTenantsController(), 'create']);
        $this->router->add('POST', '#^/platform/tenants/create$#', [new PlatformTenantsController(), 'store']);
        $this->router->add('GET', '#^/platform/tenants/(?P<id>\d+)/edit$#', [new PlatformTenantsController(), 'edit']);
        $this->router->add('POST', '#^/platform/tenants/(?P<id>\d+)/edit$#', [new PlatformTenantsController(), 'update']);
        $this->router->add('POST', '#^/platform/tenants/(?P<id>\d+)/suspend$#', [new PlatformTenantsController(), 'suspend']);
        $this->router->add('POST', '#^/platform/tenants/(?P<id>\d+)/reactivate$#', [new PlatformTenantsController(), 'reactivate']);
        $this->router->add('GET', '#^/platform/jobs$#', [new PlatformJobsController(), 'index']);
        $this->router->add('GET', '#^/platform/reports$#', [new PlatformReportsController(), 'index']);

        $arca = new PlatformArcaSettingsController();
        $this->router->add('GET', '#^/platform/settings/arca$#', [$arca, 'show']);
        $this->router->add('POST', '#^/platform/settings/arca$#', [$arca, 'save']);

        // UI
        $this->router->add('GET', '#^/$#', [new HomeController(), 'index']);
        $this->router->add('GET', '#^/login$#', [new SessionController(), 'showLogin']);
        $this->router->add('POST', '#^/login$#', [new SessionController(), 'login']);
        $this->router->add('POST', '#^/logout$#', [new SessionController(), 'logout']);

        // Registration for new tenants (platform domain recommended)
        $register = new RegisterController();
        $this->router->add('GET', '#^/register$#', [$register, 'show']);
        $this->router->add('POST', '#^/register$#', [$register, 'store']);

        // Settings: custom domain management
        $settings = new SettingsController();
        $this->router->add('GET', '#^/settings/domain$#', [$settings, 'domain']);
        $this->router->add('POST', '#^/settings/domain$#', [$settings, 'saveDomain']);

        // Settings: mail
        $mailSettings = new SettingsMailController();
        $this->router->add('GET', '#^/settings/mail$#', [$mailSettings, 'mail']);
        $this->router->add('POST', '#^/settings/mail$#', [$mailSettings, 'saveMail']);

        // Settings: users
        $usersSettings = new SettingsUsersController();
        $this->router->add('GET', '#^/settings/users$#', [$usersSettings, 'index']);
        $this->router->add('POST', '#^/settings/users$#', [$usersSettings, 'handle']);

        // Settings: WhatsApp (Chatwoot)
        $wa = new SettingsWhatsAppController();
        $this->router->add('GET', '#^/settings/whatsapp$#', [$wa, 'show']);
        $this->router->add('POST', '#^/settings/whatsapp$#', [$wa, 'save']);

        $uiComplaints = new ComplaintsController();
        $this->router->add('GET', '#^/complaints$#', [$uiComplaints, 'index']);
        $this->router->add('GET', '#^/my/complaints$#', [$uiComplaints, 'my']);
        $this->router->add('GET', '#^/complaints/new$#', [$uiComplaints, 'create']);
        $this->router->add('POST', '#^/complaints$#', [$uiComplaints, 'store']);
        $this->router->add('GET', '#^/complaints/(?P<id>\d+)$#', [$uiComplaints, 'show']);
        $this->router->add('POST', '#^/complaints/(?P<id>\d+)/responses$#', [$uiComplaints, 'respond']);

        // API v1
        $apiAuth = new ApiAuthController();
        $this->router->add('POST', '#^/api/v1/auth/token$#', [$apiAuth, 'token']);

        $apiComplaints = new ApiComplaintsController();
        $this->router->add('GET', '#^/api/v1/complaints$#', [$apiComplaints, 'index']);
        $this->router->add('POST', '#^/api/v1/complaints$#', [$apiComplaints, 'store']);
        $this->router->add('GET', '#^/api/v1/complaints/(?P<id>\d+)$#', [$apiComplaints, 'show']);
    }
}

