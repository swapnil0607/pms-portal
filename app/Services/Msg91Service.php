<?php

namespace App\Services;

use App\Core\Database;

class Msg91Service
{
    /**
     * Send Password Reset email via MSG91 Email API with dynamic template variables.
     *
     * @param string $email The recipient's email address
     * @param string $name The recipient's full name
     * @param string $resetLink The complete secure reset URL
     * @return array ['success' => bool, 'message' => string, 'response' => mixed]
     */
    public static function sendPasswordResetEmail(string $email, string $name, string $resetLink): array
    {
        $cfg = config('msg91');
        $authKey = $cfg['auth_key'] ?? '';
        $templateId = $cfg['template_id'] ?? '';
        $domain = $cfg['domain'] ?? 'eduriser.in';
        $fromEmail = $cfg['from_email'] ?? 'noreply@eduriser.in';
        $fromName = $cfg['from_name'] ?? 'EduRiser PMS';
        $expiryMinutes = (int) ($cfg['expiry_minutes'] ?? 60);

        if (empty($authKey) || $authKey === 'YOUR_MSG91_AUTH_KEY_HERE') {
            error_log('MSG91 Error: auth_key is not configured in config/msg91.php');
            return [
                'success' => false,
                'message' => 'MSG91 authentication key is not configured in config/msg91.php.',
            ];
        }

        if (empty($templateId) || $templateId === 'YOUR_MSG91_TEMPLATE_ID_HERE') {
            error_log('MSG91 Error: template_id is not configured in config/msg91.php');
            return [
                'success' => false,
                'message' => 'MSG91 template ID is not configured in config/msg91.php.',
            ];
        }

        $payload = [
            'recipients' => [
                [
                    'to' => [
                        [
                            'name' => $name,
                            'email' => $email,
                        ],
                    ],
                    'variables' => [
                        'user_name' => $name,
                        'employee_name' => $name,
                        'reset_link' => $resetLink,
                        'expiry_time' => $expiryMinutes . ' minutes',
                    ],
                ],
            ],
            'from' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'domain' => $domain,
            'template_id' => $templateId,
        ];

        $ch = curl_init('https://control.msg91.com/api/v5/email/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authkey: ' . $authKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('MSG91 cURL error: ' . $curlError);
            return [
                'success' => false,
                'message' => 'Network error connecting to MSG91: ' . $curlError,
            ];
        }

        $decoded = json_decode((string) $response, true);

        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'message' => 'Password reset email sent successfully via MSG91.',
                'response' => $decoded,
            ];
        }

        $errMsg = $decoded['message'] ?? $decoded['errors'][0] ?? ('MSG91 API returned error HTTP ' . $httpCode);
        error_log('MSG91 API error: ' . (is_array($errMsg) ? json_encode($errMsg) : $errMsg));

        return [
            'success' => false,
            'message' => is_string($errMsg) ? $errMsg : 'Failed to send email via MSG91.',
            'response' => $decoded,
        ];
    }

    /**
     * Send any MSG91 template email by key (e.g. 'task_assigned', 'renewal_alert', 'important_notice') or template ID.
     *
     * @param string $templateKeyOrId The config template key or literal template ID/slug
     * @param string $email Recipient email
     * @param string $name Recipient name
     * @param array $variables Dynamic placeholder variables
     * @return array ['success' => bool, 'message' => string, 'response' => mixed]
     */
    public static function sendTemplateEmail(string $templateKeyOrId, string $email, string $name, array $variables = []): array
    {
        $cfg = config('msg91');
        $authKey = $cfg['auth_key'] ?? '';
        $domain = $cfg['domain'] ?? 'mail.eduriser.com';
        $fromEmail = $cfg['from_email'] ?? 'no-reply@mail.eduriser.com';
        $fromName = $cfg['from_name'] ?? 'EduRiser PMS';

        // Resolve template ID if a config key was provided
        $templateId = $cfg[$templateKeyOrId . '_template_id'] ?? $cfg[$templateKeyOrId] ?? $templateKeyOrId;

        if (empty($authKey) || str_starts_with($authKey, 'YOUR_')) {
            return ['success' => false, 'message' => 'MSG91 auth_key not configured.'];
        }

        if (empty($templateId) || str_starts_with($templateId, 'YOUR_')) {
            return ['success' => false, 'message' => "MSG91 template '{$templateKeyOrId}' not configured."];
        }

        // Ensure user_name, employee_name, and date_year exist
        if (!isset($variables['user_name'])) {
            $variables['user_name'] = $name;
        }
        if (!isset($variables['employee_name'])) {
            $variables['employee_name'] = $name;
        }
        if (!isset($variables['date_year'])) {
            $variables['date_year'] = date('Y');
        }

        $payload = [
            'recipients' => [
                [
                    'to' => [
                        [
                            'name' => $name,
                            'email' => $email,
                        ],
                    ],
                    'variables' => $variables,
                ],
            ],
            'from' => [
                'name' => $fromName,
                'email' => $fromEmail,
            ],
            'domain' => $domain,
            'template_id' => $templateId,
        ];

        $ch = curl_init('https://control.msg91.com/api/v5/email/send');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'authkey: ' . $authKey,
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('MSG91 cURL error: ' . $curlError);
            return ['success' => false, 'message' => 'Network error: ' . $curlError];
        }

        $decoded = json_decode((string) $response, true);
        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Email sent via MSG91.', 'response' => $decoded];
        }

        $errMsg = $decoded['message'] ?? $decoded['errors'][0] ?? ('MSG91 error HTTP ' . $httpCode);
        error_log('MSG91 API error for ' . $templateId . ': ' . (is_array($errMsg) ? json_encode($errMsg) : $errMsg));
        return ['success' => false, 'message' => is_string($errMsg) ? $errMsg : 'Failed to send template email.'];
    }

    /**
     * Creates and stores a secure reset token for the given email, returning the unhashed token.
     */
    public static function createPasswordResetToken(string $email): string
    {
        self::ensureTableExists();

        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);

        $cfg = config('msg91');
        $expiryMinutes = (int) ($cfg['expiry_minutes'] ?? 60);
        $expiresAt = date('Y-m-d H:i:s', time() + ($expiryMinutes * 60));

        $db = Database::connection();
        $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE email = ? AND used_at IS NULL')
            ->execute([$email]);

        $stmt = $db->prepare('INSERT INTO password_resets (email, token_hash, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$email, $tokenHash, $expiresAt]);

        return $rawToken;
    }

    /**
     * Validates a password reset token and returns the matching record if valid.
     */
    public static function validateResetToken(string $rawToken): ?array
    {
        self::ensureTableExists();

        if (trim($rawToken) === '') {
            return null;
        }

        $tokenHash = hash('sha256', $rawToken);
        $db = Database::connection();
        $stmt = $db->prepare(
            'SELECT * FROM password_resets 
             WHERE token_hash = ? 
               AND used_at IS NULL 
               AND expires_at > NOW() 
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$tokenHash]);
        $record = $stmt->fetch();

        return $record ?: null;
    }

    /**
     * Marks a reset token as used.
     */
    public static function markTokenUsed(string $rawToken): void
    {
        $tokenHash = hash('sha256', $rawToken);
        $db = Database::connection();
        $stmt = $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE token_hash = ?');
        $stmt->execute([$tokenHash]);
    }

    /**
     * Ensures the password_resets table exists.
     */
    public static function ensureTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $db = Database::connection();
            $db->exec(
                'CREATE TABLE IF NOT EXISTS password_resets (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    email VARCHAR(160) NOT NULL,
                    token_hash VARCHAR(64) NOT NULL,
                    expires_at DATETIME NOT NULL,
                    used_at DATETIME DEFAULT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_token (token_hash),
                    INDEX idx_email (email)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;'
            );
            $checked = true;
        } catch (\Throwable $e) {
            error_log('Failed to ensure password_resets table: ' . $e->getMessage());
        }
    }
}
