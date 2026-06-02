<?php

declare(strict_types=1);

namespace Prometheus\Services;

final class EncryptionService
{
    private const CIPHER = 'aes-256-gcm';

    public function encrypt(string $plainText, string $key): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipherText = openssl_encrypt($plainText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new \RuntimeException('Cifratura non riuscita.');
        }

        return base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(string $encodedPayload, string $key): string
    {
        $payload = base64_decode($encodedPayload, true);

        if ($payload === false || strlen($payload) < 28) {
            throw new \InvalidArgumentException('Payload cifrato non valido.');
        }

        $iv = substr($payload, 0, 12);
        $tag = substr($payload, 12, 16);
        $cipherText = substr($payload, 28);
        $plainText = openssl_decrypt($cipherText, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plainText === false) {
            throw new \RuntimeException('Decifratura non riuscita.');
        }

        return $plainText;
    }
}
