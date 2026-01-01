<?php
declare(strict_types=1);

namespace App\Services;

final class ChatwootClient
{
    /** @return array{ok:bool,status:int,body:string,error?:string,json?:array<string,mixed>} */
    private static function request(string $method, string $url, string $token, ?array $jsonBody = null): array
    {
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json',
            'api_access_token: ' . $token,
        ];

        $payload = $jsonBody ? (json_encode($jsonBody, JSON_UNESCAPED_UNICODE) ?: '{}') : null;

        if (!function_exists('curl_init')) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_required'];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['ok' => false, 'status' => 0, 'body' => '', 'error' => 'curl_init_failed'];
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 20,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        // curl_close() is deprecated since PHP 8.5 and has no effect since PHP 8.0.

        if ($body === false) {
            return ['ok' => false, 'status' => $status, 'body' => '', 'error' => $err ?: 'curl_exec_failed'];
        }
        $decoded = json_decode((string)$body, true);
        $ok = $status >= 200 && $status < 300;
        return $ok
            ? ['ok' => true, 'status' => $status, 'body' => (string)$body, 'json' => is_array($decoded) ? $decoded : null]
            : ['ok' => false, 'status' => $status, 'body' => (string)$body, 'error' => 'http_' . $status, 'json' => is_array($decoded) ? $decoded : null];
    }

    /**
     * Ensures a contact exists for a phone number.
     * @return int contact_id
     */
    public static function ensureContact(string $baseUrl, int $accountId, string $token, string $phone, ?string $name = null, ?string $email = null): int
    {
        $baseUrl = rtrim($baseUrl, '/');
        $payload = [
            'name' => $name ?: $phone,
            'email' => $email,
            'phone_number' => $phone,
        ];
        // Create is idempotent enough for our use; if conflict occurs, Chatwoot usually returns existing contact.
        $res = self::request('POST', $baseUrl . '/api/v1/accounts/' . $accountId . '/contacts', $token, $payload);
        if (!$res['ok']) {
            throw new \RuntimeException('chatwoot_contact_failed:' . ($res['error'] ?? 'unknown'));
        }
        $json = $res['json'] ?? null;
        if (is_array($json) && isset($json['payload']['contact']['id'])) {
            return (int)$json['payload']['contact']['id'];
        }
        if (is_array($json) && isset($json['id'])) {
            return (int)$json['id'];
        }
        throw new \RuntimeException('chatwoot_contact_unexpected');
    }

    /** @return int conversation_id */
    public static function createConversation(string $baseUrl, int $accountId, int $inboxId, string $token, int $contactId): int
    {
        $baseUrl = rtrim($baseUrl, '/');
        $payload = [
            'inbox_id' => $inboxId,
            'contact_id' => $contactId,
        ];
        $res = self::request('POST', $baseUrl . '/api/v1/accounts/' . $accountId . '/conversations', $token, $payload);
        if (!$res['ok']) {
            throw new \RuntimeException('chatwoot_conversation_failed:' . ($res['error'] ?? 'unknown'));
        }
        $json = $res['json'] ?? null;
        if (is_array($json) && isset($json['id'])) {
            return (int)$json['id'];
        }
        if (is_array($json) && isset($json['payload']['id'])) {
            return (int)$json['payload']['id'];
        }
        throw new \RuntimeException('chatwoot_conversation_unexpected');
    }

    public static function sendMessage(string $baseUrl, int $accountId, string $token, int $conversationId, string $content): void
    {
        $baseUrl = rtrim($baseUrl, '/');
        $payload = [
            'content' => $content,
            'message_type' => 'outgoing',
            'private' => false,
        ];
        $res = self::request('POST', $baseUrl . '/api/v1/accounts/' . $accountId . '/conversations/' . $conversationId . '/messages', $token, $payload);
        if (!$res['ok']) {
            throw new \RuntimeException('chatwoot_message_failed:' . ($res['error'] ?? 'unknown'));
        }
    }
}
