<?php

namespace CaddyPanel\Security;

class Encryptor
{
    public function __construct(private string $key)
    {
    }

    public function encrypt(string $plainText): string
    {
        if (!function_exists('openssl_encrypt')) {
            throw new \RuntimeException('PHP OpenSSL extension is required for password encryption.');
        }

        $iv = random_bytes(12);
        $tag = '';
        $cipherText = \openssl_encrypt($plainText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($cipherText === false) {
            throw new \RuntimeException('Encryption failed.');
        }

        return base64_encode($iv . $tag . $cipherText);
    }

    public function decrypt(string $payload): string
    {
        if (!function_exists('openssl_decrypt')) {
            throw new \RuntimeException('PHP OpenSSL extension is required for password decryption.');
        }

        $data = base64_decode($payload, true);

        if ($data === false || strlen($data) < 29) {
            throw new \RuntimeException('Invalid encrypted payload.');
        }

        $iv = substr($data, 0, 12);
        $tag = substr($data, 12, 16);
        $cipherText = substr($data, 28);
        $plainText = \openssl_decrypt($cipherText, 'aes-256-gcm', $this->key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($plainText === false) {
            throw new \RuntimeException('Decryption failed.');
        }

        return $plainText;
    }
}
