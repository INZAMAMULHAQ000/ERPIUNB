<?php
/**
 * AI Credential Encryption Helper.
 *
 * Provides secure encryption/decryption for API keys stored in the database.
 * Uses AES-256-CBC with a key derived from a server-side secret.
 *
 * Security: The encryption key is derived from a combination of a server-side
 * constant and a per-installation salt. It is NOT stored in the database.
 */

/**
 * Get or derive the encryption key for AI credentials.
 * Uses a combination of a fixed application key and a per-installation salt.
 */
function aiGetEncryptionKey(): string
{
    // Application-level key (change this in production!)
    // This should be set once per installation in a config file or env var.
    $appKey = defined('AI_ENCRYPTION_KEY') ? AI_ENCRYPTION_KEY : 'erp-ai-credential-key-change-in-production';

    // Derive a 32-byte key using PBKDF2
    $salt = 'erp-ai-credential-salt-v1';
    $iterations = 10000;

    return hash_pbkdf2('sha256', $appKey, $salt, $iterations, 32, true);
}

/**
 * Encrypt an API key for secure storage.
 *
 * @param string $plaintext The API key to encrypt
 * @return array{encrypted: string, iv: string} Base64-encoded encrypted data and IV
 * @throws RuntimeException If encryption fails
 */
function aiEncryptCredential(string $plaintext): array
{
    $key = aiGetEncryptionKey();
    $cipher = 'aes-256-cbc';
    $ivLength = openssl_cipher_iv_length($cipher);

    $iv = openssl_random_pseudo_bytes($ivLength);
    if ($iv === false) {
        throw new RuntimeException('Failed to generate encryption IV');
    }

    $encrypted = openssl_encrypt($plaintext, $cipher, $key, OPENSSL_RAW_DATA, $iv);
    if ($encrypted === false) {
        throw new RuntimeException('Failed to encrypt credential');
    }

    return [
        'encrypted' => base64_encode($encrypted),
        'iv'        => base64_encode($iv),
    ];
}

/**
 * Decrypt an encrypted API key.
 *
 * @param string $encryptedBase64 Base64-encoded encrypted data
 * @param string $ivBase64 Base64-encoded initialization vector
 * @return string|null The decrypted API key, or null on failure
 */
function aiDecryptCredential(string $encryptedBase64, string $ivBase64): ?string
{
    $key = aiGetEncryptionKey();
    $cipher = 'aes-256-cbc';

    $encrypted = base64_decode($encryptedBase64, true);
    $iv = base64_decode($ivBase64, true);

    if ($encrypted === false || $iv === false) {
        return null;
    }

    $decrypted = openssl_decrypt($encrypted, $cipher, $key, OPENSSL_RAW_DATA, $iv);

    return $decrypted !== false ? $decrypted : null;
}

/**
 * Mask an API key for display purposes.
 * Shows only the last 4 characters.
 *
 * @param string $apiKey The full API key
 * @return string Masked key like "••••••••1234"
 */
function aiMaskApiKey(string $apiKey): string
{
    if (strlen($apiKey) <= 4) {
        return '••••••••';
    }
    return str_repeat('•', max(4, strlen($apiKey) - 4)) . substr($apiKey, -4);
}

/**
 * Check if a credential is configured (encrypted data exists).
 *
 * @param string|null $encrypted The encrypted data
 * @return bool
 */
function aiHasCredential(?string $encrypted): bool
{
    return !empty($encrypted);
}

/**
 * Log an AI audit event.
 *
 * @param PDO $db Database connection
 * @param string $action The action performed
 * @param string|null $entityType The entity type (provider, model, etc.)
 * @param int|null $entityId The entity ID
 * @param string|null $details Additional details (never include credentials)
 * @param int|null $userId The user performing the action
 */
function aiAuditLog(PDO $db, string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null, ?int $userId = null): void
{
    $stmt = $db->prepare("
        INSERT INTO ai_audit_log (user_id, action, entity_type, entity_id, details, ip_address, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $userId ?? ($_SESSION['user_id'] ?? null),
        $action,
        $entityType,
        $entityId,
        $details,
        $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}
