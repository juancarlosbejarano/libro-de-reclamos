<?php
declare(strict_types=1);

namespace App\Controllers\Api;

use App\Http\Request;
use App\Http\Response;
use App\Models\ApiToken;
use App\Models\Complaint;
use App\Services\TenantResolver;

final class ApiComplaintsController
{
    public function index(Request $request): Response
    {
        $auth = ApiToken::authenticateRequest($request);
        if (!$auth) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $tenantId = TenantResolver::tenantId();
        $items = Complaint::listForTenant($tenantId, 100);
        return Response::json(['data' => $items]);
    }

    public function show(Request $request, array $params): Response
    {
        $auth = ApiToken::authenticateRequest($request);
        if (!$auth) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $tenantId = TenantResolver::tenantId();
        $id = (int)($params['id'] ?? 0);
        $item = Complaint::findForTenant($tenantId, $id);
        if (!$item) {
            return Response::json(['error' => 'not_found'], 404);
        }
        return Response::json(['data' => $item]);
    }

    public function store(Request $request): Response
    {
        $auth = ApiToken::authenticateRequest($request);
        if (!$auth) {
            return Response::json(['error' => 'unauthorized'], 401);
        }
        $tenantId = TenantResolver::tenantId();
        $json = $request->json();
        if (!$json) {
            return Response::json(['error' => 'invalid_json'], 400);
        }
        $subject = trim((string)($json['subject'] ?? ''));
        $message = trim((string)($json['message'] ?? ''));
        $customerName = trim((string)($json['customer_name'] ?? ''));
        $customerEmail = trim((string)($json['customer_email'] ?? ''));
        $customerPhone = trim((string)($json['customer_phone'] ?? ''));
        if ($subject === '' || $message === '') {
            return Response::json(['error' => 'validation_failed'], 422);
        }

        if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::json(['error' => 'validation_failed'], 422);
        }
        if ($customerPhone !== '') {
            $normalized = preg_replace('/[^0-9+]/', '', $customerPhone);
            $normalized = is_string($normalized) ? $normalized : $customerPhone;
            if (!preg_match('/^\+?[0-9]{8,15}$/', $normalized)) {
                return Response::json(['error' => 'validation_failed'], 422);
            }
            $customerPhone = $normalized;
        }

        $id = Complaint::createForTenant(
            $tenantId,
            $subject,
            $message,
            (int)$auth['user_id'],
            null,
            $customerName !== '' ? $customerName : null,
            $customerEmail !== '' ? $customerEmail : null,
            $customerPhone !== '' ? $customerPhone : null
        );
        $item = Complaint::findForTenant($tenantId, $id);
        return Response::json(['data' => $item], 201);
    }
}
