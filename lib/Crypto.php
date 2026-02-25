<?php
declare(strict_types=1);

class Crypto {

    private const HEADER = "SCRUB1";

    public static function encryptFile(string $plainPath, string $encPath, string $passphrase, string $aad = ''): void {
        $data = file_get_contents($plainPath);
        if ($data === false) {
            throw new RuntimeException('Failed to read plaintext file');
        }

        $key = hash('sha256', $passphrase, true);
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt(
            $data,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($cipher === false) {
            throw new RuntimeException('Encryption failed');
        }

        $payload = self::HEADER . $iv . $tag . $cipher;
        if (file_put_contents($encPath, $payload, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write encrypted file');
        }
    }

    public static function decryptFile(string $encPath, string $plainPath, string $passphrase, string $aad = ''): void {
        $payload = file_get_contents($encPath);
        if ($payload === false) {
            throw new RuntimeException('Failed to read encrypted file');
        }

        $headerLen = strlen(self::HEADER);
        if (strlen($payload) < $headerLen + 12 + 16) {
            throw new RuntimeException('Encrypted file is too short');
        }

        $header = substr($payload, 0, $headerLen);
        if ($header !== self::HEADER) {
            throw new RuntimeException('Invalid encrypted file header');
        }

        $iv = substr($payload, $headerLen, 12);
        $tag = substr($payload, $headerLen + 12, 16);
        $cipher = substr($payload, $headerLen + 12 + 16);

        $key = hash('sha256', $passphrase, true);
        $plain = openssl_decrypt(
            $cipher,
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            $aad
        );

        if ($plain === false) {
            throw new RuntimeException('Decryption failed');
        }

        if (file_put_contents($plainPath, $plain, LOCK_EX) === false) {
            throw new RuntimeException('Failed to write plaintext file');
        }
    }
}
