<?php
declare(strict_types=1);

namespace App\Support;

final class Crypto
{
    /**
     * Encrypts a secret for DB storage.
     * Output format: base64(iv).base64(tag).base64(ciphertext)
     */
    public static function encrypt(string $plaintext): string
    {
        $key = self::keyBytes();
        $iv = random_bytes(12); // GCM recommended
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('encrypt_failed');
        }
        return base64_encode($iv) . '.' . base64_encode($tag) . '.' . base64_encode($ciphertext);
    }

    public static function decrypt(string $packed): string
    {
        $key = self::keyBytes();
        $parts = explode('.', $packed);
        if (count($parts) !== 3) {
            throw new \RuntimeException('decrypt_invalid_format');
        }
        [$ivB64, $tagB64, $ctB64] = $parts;
        $iv = base64_decode($ivB64, true);
        $tag = base64_decode($tagB64, true);
        $ct = base64_decode($ctB64, true);
        if ($iv === false || $tag === false || $ct === false) {
            throw new \RuntimeException('decrypt_invalid_base64');
        }
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new \RuntimeException('decrypt_failed');
        }
        return $pt;
    }

    /** @return string raw bytes */
    private static function keyBytes(): string
    {
        $raw = Env::get('APP_KEY', '') ?? '';
        $raw = trim((string)$raw);
        if ($raw === '') {
            throw new \RuntimeException('APP_KEY missing');
        }
        if (str_starts_with($raw, 'base64:')) {
            $raw = substr($raw, 7);
        }
        $bytes = base64_decode($raw, true);
        if ($bytes === false || strlen($bytes) !== 32) {
            throw new \RuntimeException('APP_KEY invalid (expected 32 bytes base64)');
        }
        return $bytes;
    }
}
