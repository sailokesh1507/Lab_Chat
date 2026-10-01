<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'config.php';

const CHAT_CIPHER = 'aes-256-gcm';

function chatEncryptionKey(): string
{
    $key = base64_decode(CHAT_ENCRYPTION_KEY, true);
    if ($key === false || strlen($key) !== 32) {
        throw new RuntimeException('The chat encryption key is invalid.');
    }
    return $key;
}

function encryptChatValue(string $value): string
{
    $ivLength = openssl_cipher_iv_length(CHAT_CIPHER);
    $iv = random_bytes($ivLength);
    $tag = '';
    $ciphertext = openssl_encrypt($value, CHAT_CIPHER, chatEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('The chat message could not be encrypted.');
    }

    return 'enc:v1:' . base64_encode($iv . $tag . $ciphertext);
}

function decryptChatValue(string $value): string
{
    if (!str_starts_with($value, 'enc:v1:')) {
        return $value;
    }

    $encoded = base64_decode(substr($value, 7), true);
    $ivLength = openssl_cipher_iv_length(CHAT_CIPHER);
    if ($encoded === false || strlen($encoded) <= $ivLength + 16) {
        return '[message unavailable]';
    }

    $iv = substr($encoded, 0, $ivLength);
    $tag = substr($encoded, $ivLength, 16);
    $ciphertext = substr($encoded, $ivLength + 16);
    $plaintext = openssl_decrypt($ciphertext, CHAT_CIPHER, chatEncryptionKey(), OPENSSL_RAW_DATA, $iv, $tag);
    return $plaintext === false ? '[message unavailable]' : $plaintext;
}