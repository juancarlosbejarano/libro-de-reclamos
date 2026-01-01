<?php
declare(strict_types=1);

namespace App\Controllers\Ui;

use App\Http\Request;
use App\Http\Response;
use App\Models\Complaint;
use App\Models\ComplaintResponse;
use App\Models\User;
use App\Services\ChatwootClient;
use App\Services\MailConfig;
use App\Services\SmtpMailer;
use App\Services\TenantResolver;
use App\Services\WhatsAppConfig;
use App\Support\Csrf;
use App\Views\View;

final class ComplaintsController
{
    public function index(Request $request): Response
    {
        $tenantId = TenantResolver::tenantId();
        $complaints = Complaint::listForTenant($tenantId, 50);
        return Response::html(View::render('complaints/index', ['complaints' => $complaints]));
    }

    public function my(Request $request): Response
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser) {
            return Response::redirect('/login');
        }
        $role = (string)($currentUser['role'] ?? '');
        if ($role !== 'user') {
            return Response::redirect('/complaints');
        }
        $tenantId = TenantResolver::tenantId();
        $complaints = Complaint::listForExternalUser(
            $tenantId,
            (int)$currentUser['id'],
            (string)$currentUser['email'],
            50
        );
        return Response::html(View::render('complaints/index', ['complaints' => $complaints, 'title' => 'complaints.my_title']));
    }

    public function create(Request $request): Response
    {
        return Response::html(View::render('complaints/create'));
    }

    public function store(Request $request): Response
    {
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::html(View::render('complaints/create', ['error' => 'CSRF inválido']), 400);
        }

        $subject = trim((string)($request->post['subject'] ?? ''));
        $message = trim((string)($request->post['message'] ?? ''));
        $customerName = trim((string)($request->post['customer_name'] ?? ''));
        $customerEmail = trim((string)($request->post['customer_email'] ?? ''));
        $customerPhone = trim((string)($request->post['customer_phone'] ?? ''));
        if ($subject === '' || $message === '') {
            return Response::html(View::render('complaints/create', ['error' => 'Completa asunto y mensaje']), 422);
        }

        if ($customerEmail !== '' && !filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
            return Response::html(View::render('complaints/create', ['error' => 'Email inválido']), 422);
        }

        if ($customerPhone !== '') {
            $normalized = preg_replace('/[^0-9+]/', '', $customerPhone);
            $normalized = is_string($normalized) ? $normalized : $customerPhone;
            if (!preg_match('/^\+?[0-9]{8,15}$/', $normalized)) {
                return Response::html(View::render('complaints/create', ['error' => 'Teléfono inválido (usa formato internacional)']), 422);
            }
            $customerPhone = $normalized;
        }

        $tenantId = TenantResolver::tenantId();
        $userId = isset($_SESSION['user']) ? (int)$_SESSION['user']['id'] : null;
        $currentUser = $_SESSION['user'] ?? null;
        if ($currentUser && (string)($currentUser['role'] ?? '') === 'user') {
            if ($customerEmail === '') {
                $customerEmail = (string)($currentUser['email'] ?? '');
            }
            if ($customerName === '') {
                $customerName = (string)($currentUser['email'] ?? '');
            }
        }

        $attachment = $request->files['attachment'] ?? null;
        $attachmentInfo = is_array($attachment) ? $attachment : null;

        $id = Complaint::createForTenant(
            $tenantId,
            $subject,
            $message,
            $userId,
            $attachmentInfo,
            $customerName !== '' ? $customerName : null,
            $customerEmail !== '' ? $customerEmail : null,
            $customerPhone !== '' ? $customerPhone : null
        );

        // Notify tenant admins (best-effort)
        try {
            $cfg = MailConfig::forTenant($tenantId);
            $to = User::listAdminEmails($tenantId);
            $subj = 'Nuevo reclamo #' . $id;
            $body = "Se registró un nuevo reclamo\n\n" .
                "Asunto: {$subject}\n" .
                "Mensaje:\n{$message}\n\n" .
                "Ver: " . ($request->headers['host'] ?? '') . "/complaints/{$id}\n";
            SmtpMailer::send($cfg, $to, $subj, $body);
        } catch (\Throwable $e) {
            // Swallow: do not block complaint creation
        }
        return Response::redirect('/complaints/' . $id);
    }

    public function show(Request $request, array $params): Response
    {
        $tenantId = TenantResolver::tenantId();
        $id = (int)($params['id'] ?? 0);
        $complaint = Complaint::findForTenant($tenantId, $id);
        $currentUser = $_SESSION['user'] ?? null;
        if ($complaint && $currentUser && (string)($currentUser['role'] ?? '') === 'user') {
            if (!Complaint::belongsToExternalUser($complaint, (int)$currentUser['id'], (string)$currentUser['email'])) {
                return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
            }
        }
        $responses = $complaint ? ComplaintResponse::listForComplaint($tenantId, $id) : [];
        return Response::html(View::render('complaints/show', ['complaint' => $complaint, 'responses' => $responses]));
    }

    public function respond(Request $request, array $params): Response
    {
        $currentUser = $_SESSION['user'] ?? null;
        if (!$currentUser) {
            return Response::redirect('/login');
        }
        $role = (string)($currentUser['role'] ?? '');
        if (!in_array($role, ['admin', 'staff'], true)) {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }
        if (!Csrf::verify($request->post['_csrf'] ?? null)) {
            return Response::redirect('/complaints/' . (string)($params['id'] ?? '0'));
        }

        $tenantId = TenantResolver::tenantId();
        $complaintId = (int)($params['id'] ?? 0);
        $complaint = Complaint::findForTenant($tenantId, $complaintId);
        if (!$complaint) {
            return Response::html(View::render('errors/404', ['path' => $request->path]), 404);
        }

        $message = trim((string)($request->post['message'] ?? ''));
        if ($message === '') {
            return Response::redirect('/complaints/' . (string)$complaintId);
        }

        $responseId = ComplaintResponse::create($tenantId, $complaintId, (int)$currentUser['id'], $message);

        // Email to customer (best-effort)
        try {
            $customerEmail = trim((string)($complaint['customer_email'] ?? ''));
            if ($customerEmail !== '' && filter_var($customerEmail, FILTER_VALIDATE_EMAIL)) {
                $cfg = MailConfig::forTenant($tenantId);
                $subj = 'Respuesta a tu reclamo #' . $complaintId;
                $body = "Asunto: " . (string)($complaint['subject'] ?? '') . "\n\n" . $message;
                SmtpMailer::send($cfg, [$customerEmail], $subj, $body);
                ComplaintResponse::markEmailSent($tenantId, $responseId);
            }
        } catch (\Throwable $e) {
            try {
                ComplaintResponse::markEmailFailed($tenantId, $responseId, $e->getMessage());
            } catch (\Throwable $ignored) {
            }
            // Swallow
        }

        // WhatsApp via Chatwoot (best-effort)
        try {
            $wa = WhatsAppConfig::forTenant($tenantId);
            $phone = trim((string)($complaint['customer_phone'] ?? ''));
            if ($wa && $phone !== '') {
                $conversationId = (int)($complaint['chatwoot_conversation_id'] ?? 0);
                if ($conversationId <= 0) {
                    $contactId = ChatwootClient::ensureContact(
                        $wa['base_url'],
                        $wa['account_id'],
                        $wa['token'],
                        $phone,
                        (string)($complaint['customer_name'] ?? ''),
                        (string)($complaint['customer_email'] ?? '')
                    );
                    $conversationId = ChatwootClient::createConversation($wa['base_url'], $wa['account_id'], $wa['inbox_id'], $wa['token'], $contactId);
                    Complaint::setChatwootConversationId($tenantId, $complaintId, $conversationId);
                }
                $content = "Respuesta a tu reclamo #{$complaintId}:\n\n" . $message;
                ChatwootClient::sendMessage($wa['base_url'], $wa['account_id'], $wa['token'], $conversationId, $content);
                ComplaintResponse::markWhatsAppSent($tenantId, $responseId);
            }
        } catch (\Throwable $e) {
            try {
                ComplaintResponse::markWhatsAppFailed($tenantId, $responseId, $e->getMessage());
            } catch (\Throwable $ignored) {
            }
            // Swallow
        }

        return Response::redirect('/complaints/' . (string)$complaintId);
    }
}
