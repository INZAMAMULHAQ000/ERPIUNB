<?php
/**
 * AI F27 — Document Security Service.
 *
 * Handles prompt injection defense, malicious file protection,
 * sensitive document protection, and document retention.
 *
 * Covers Sections 19 (Prompt Injection), 20 (Malicious File),
 * 21 (Sensitive Document), and 22 (Document Retention).
 */
class AIDocumentSecurityService
{
    // Allowed MIME types
    private static array $allowedMimeTypes = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/tiff',
        'image/bmp',
        'image/webp',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'text/plain',
        'text/csv',
        'text/html',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    // Dangerous file extensions that should never be processed
    private static array $dangerousExtensions = [
        'php', 'php3', 'php4', 'php5', 'phtml', 'pht',
        'js', 'vbs', 'vbe', 'wsf', 'wsh',
        'exe', 'com', 'bat', 'cmd', 'scr', 'pif',
        'sh', 'bash', 'csh', 'ksh',
        'py', 'rb', 'pl', 'cgi',
        'jsp', 'jspx', 'asp', 'aspx', 'cer', 'cfm',
        'msi', 'dll', 'so', 'dylib',
    ];

    /**
     * Validate a file upload for security (Section 20).
     * Returns safe/unsafe with reasons.
     */
    public static function validateUpload(PDO $db, array $fileData): array
    {
        $result = [
            'safe' => true,
            'errors' => [],
            'warnings' => [],
        ];

        $fileName = $fileData['name'] ?? '';
        $mimeType = $fileData['type'] ?? '';
        $fileSize = $fileData['size'] ?? 0;
        $fileContent = $fileData['tmp_name'] ?? '';

        // 1. Check file extension
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (in_array($ext, self::$dangerousExtensions)) {
            $result['safe'] = false;
            $result['errors'][] = "Dangerous file extension: .{$ext}";
            self::logSecurityEvent($db, null, 'malicious_file', 'critical', [
                'reason' => 'dangerous_extension',
                'extension' => $ext,
                'file_name' => $fileName,
            ], $fileName, $mimeType);
        }

        // 2. Validate MIME type
        if ($mimeType && !in_array($mimeType, self::$allowedMimeTypes)) {
            $result['safe'] = false;
            $result['errors'][] = "Disallowed MIME type: {$mimeType}";
            self::logSecurityEvent($db, null, 'malicious_file', 'high', [
                'reason' => 'invalid_mime',
                'mime_type' => $mimeType,
                'file_name' => $fileName,
            ], $fileName, $mimeType);
        }

        // 3. Check file size against admin config
        $maxSize = self::getConfigValue($db, 'max_file_size_mb', 50) * 1024 * 1024;
        if ($fileSize > $maxSize) {
            $result['safe'] = false;
            $result['errors'][] = "File size exceeds maximum: " . round($fileSize / 1024 / 1024, 1) . "MB > {$maxSize}MB";
        }

        // 4. MIME sniffing — read first bytes and verify
        if ($fileContent && is_readable($fileContent)) {
            $header = fread($fileContent, 8);
            rewind($fileContent);

            $magicSignatures = [
                '%PDF' => 'application/pdf',
                "\xFF\xD8\xFF" => 'image/jpeg',
                "\x89PNG" => 'image/png',
                'II\x2A\x00' => 'image/tiff',
                'MM\x00\x2A' => 'image/tiff',
            ];

            foreach ($magicSignatures as $sig => $expectedMime) {
                if (str_starts_with($header, $sig)) {
                    // Verify MIME matches signature
                    if ($mimeType && $mimeType !== $expectedMime) {
                        $result['warnings'][] = "MIME type mismatch: declared {$mimeType}, detected {$expectedMime}";
                    }
                    break;
                }
            }

            // Check for embedded executables in PDFs
            if (str_starts_with($header, '%PDF')) {
                $content = fread($fileContent, min(1024 * 100, filesize($fileContent)));
                if (preg_match('/(?:\/JavaScript|\/JS|\/OpenAction|\/Launch)/i', $content)) {
                    $result['warnings'][] = 'PDF contains JavaScript or auto-execution — flagged for review';
                    self::logSecurityEvent($db, null, 'malicious_file', 'high', [
                        'reason' => 'pdf_contains_javascript',
                        'file_name' => $fileName,
                    ], $fileName, $mimeType);
                }
            }
        }

        // 5. Check for macro indicators in Office documents
        if ($ext === 'docx' || $ext === 'xlsx') {
            if ($fileContent && is_readable($fileContent)) {
                $zip = @zip_open($fileContent);
                if ($zip) {
                    $hasMacros = false;
                    while ($entry = @zip_read($zip)) {
                        $entryName = zip_entry_name($entry);
                        if (str_contains($entryName, 'vbaProject') || str_contains($entryName, 'macro')) {
                            $hasMacros = true;
                            break;
                        }
                    }
                    @zip_close($zip);

                    if ($hasMacros) {
                        $result['warnings'][] = 'Office document contains macros — flagged for review';
                        self::logSecurityEvent($db, null, 'malicious_file', 'medium', [
                            'reason' => 'office_macros_detected',
                            'file_name' => $fileName,
                        ], $fileName, $mimeType);
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Scan document text for prompt injection (Section 19).
     * Treats document contents as untrusted external data.
     */
    public static function scanForInjection(PDO $db, int $documentId, string $text): array
    {
        if (!class_exists('AIInjectionDetectionService')) {
            return ['safe' => true, 'injections' => []];
        }

        $analysis = AIInjectionDetectionService::analyze(
            $db,
            $text,
            AIInjectionDetectionService::TRUST_UNTRUSTED_DOC
        );

        if (!$analysis['safe']) {
            self::logSecurityEvent($db, $documentId, 'injection_detected',
                $analysis['action'] === 'block' ? 'critical' : 'high', [
                    'injections' => $analysis['injections_detected'],
                    'action' => $analysis['action'],
                    'max_confidence' => $analysis['max_confidence'],
                ]
            );
        }

        return [
            'safe' => $analysis['safe'],
            'injections' => $analysis['injections_detected'],
            'action' => $analysis['action'],
            'sanitized_text' => $analysis['sanitized_content'],
        ];
    }

    /**
     * Apply F24 data classification to document (Section 21).
     */
    public static function classifyDocument(PDO $db, int $documentId, string $text, array $context = []): array
    {
        if (!class_exists('AIDataClassificationService')) {
            return ['classification' => 'internal', 'masking_required' => false];
        }

        $classification = AIDataClassificationService::classifyContent($text, $context);

        // Update document classification
        AIDocumentService::update($db, $documentId, [
            'is_sensitive' => $classification['masking_required'] ? 1 : 0,
            'sensitive_category' => $classification['classification'],
            'classification_confidence' => $classification['confidence'],
            'classification_reason' => implode(', ', $classification['reasons']),
        ]);

        if ($classification['masking_required']) {
            self::logSecurityEvent($db, $documentId, 'classification_violation', 'medium', [
                'classification' => $classification['classification'],
                'reasons' => $classification['reasons'],
                'message' => 'Document classified as sensitive/restricted',
            ]);
        }

        return $classification;
    }

    /**
     * Check if document can be sent to external provider (Section 21).
     */
    public static function canProcessExternally(PDO $db, int $companyId, int $documentId): array
    {
        // Check admin config
        $allowed = self::getConfigValue($db, 'external_processing_allowed', true, $companyId);
        if (!$allowed) {
            return ['allowed' => false, 'reason' => 'External processing disabled by administrator'];
        }

        // Check document sensitivity
        return AIDocumentService::canProcessExternally($db, $companyId, $documentId);
    }

    /**
     * Check if secret/credential content exists in document (Section 21).
     */
    public static function scanForSecrets(string $text): array
    {
        if (!class_exists('AISecretProtectionService')) {
            return ['has_secrets' => false];
        }

        return AISecretProtectionService::containsSecrets($text);
    }

    /**
     * Enforce document retention policy (Section 22).
     */
    public static function enforceRetention(PDO $db, int $companyId): array
    {
        $results = ['archived' => 0, 'deleted' => 0, 'errors' => 0];

        // Get active retention policies
        $stmt = $db->prepare("
            SELECT * FROM ai_document_retention_policies
            WHERE company_id = ? AND enabled = 1
        ");
        $stmt->execute([$companyId]);
        $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($policies as $policy) {
            try {
                // Archive documents past archive threshold
                if ($policy['archive_after_days']) {
                    $archiveDate = date('Y-m-d', strtotime("-{$policy['archive_after_days']} days"));
                    $db->prepare("
                        UPDATE ai_documents SET processing_status = 'archived', status = 'archived'
                        WHERE company_id = ? AND document_type = ? AND status = 'active'
                        AND created_at < ? AND processing_status != 'archived'
                    ")->execute([$companyId, $policy['document_type'], $archiveDate]);
                    $results['archived'] += $db->rowCount();
                }

                // Delete documents past delete threshold (if not under legal hold)
                if ($policy['delete_after_days'] && !$policy['legal_hold_enabled']) {
                    $deleteDate = date('Y-m-d', strtotime("-{$policy['delete_after_days']} days"));
                    $db->prepare("
                        UPDATE ai_documents SET status = 'deleted'
                        WHERE company_id = ? AND document_type = ? AND status IN ('active', 'archived')
                        AND created_at < ?
                    ")->execute([$companyId, $policy['document_type'], $deleteDate]);
                    $results['deleted'] += $db->rowCount();
                }
            } catch (\Throwable $e) {
                $results['errors']++;
            }
        }

        return $results;
    }

    /**
     * Log a security event (Section 24: F23 observability).
     */
    public static function logSecurityEvent(
        PDO $db,
        ?int $documentId,
        string $eventType,
        string $severity,
        array $details = [],
        ?string $fileName = null,
        ?string $mimeType = null
    ): void {
        try {
            $db->prepare("
                INSERT INTO ai_document_security_events
                    (company_id, document_id, event_type, severity, details_json,
                     file_name, mime_type, ip_address, user_agent, blocked)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $_SESSION['company_id'] ?? 1,
                $documentId,
                $eventType,
                $severity,
                json_encode($details),
                $fileName,
                $mimeType,
                $_SERVER['REMOTE_ADDR'] ?? null,
                substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
                ($severity === 'critical' || $severity === 'high') ? 1 : 0,
            ]);

            // Also record as F23 observability trace
            if (class_exists('AIIntelligenceService')) {
                AIIntelligenceService::recordObservabilityTrace($db, 'document_security_event', [
                    'document_id' => $documentId,
                    'event_type' => $eventType,
                    'severity' => $severity,
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Get security events for a document.
     */
    public static function getSecurityEvents(PDO $db, int $documentId): array
    {
        $stmt = $db->prepare("
            SELECT * FROM ai_document_security_events
            WHERE document_id = ?
            ORDER BY created_at DESC
        ");
        $stmt->execute([$documentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get security events for a company.
     */
    public static function getCompanySecurityEvents(PDO $db, int $companyId, string $severity = null): array
    {
        $sql = "SELECT * FROM ai_document_security_events WHERE company_id = ?";
        $params = [$companyId];

        if ($severity) {
            $sql .= " AND severity = ?";
            $params[] = $severity;
        }

        $sql .= " ORDER BY created_at DESC LIMIT 100";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get admin config value.
     */
    private static function getConfigValue(PDO $db, string $key, $default = null, int $companyId = 1)
    {
        try {
            $stmt = $db->prepare("SELECT config_value, config_type FROM ai_document_admin_config WHERE company_id = ? AND config_key = ?");
            $stmt->execute([$companyId, $key]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) return $default;

            return match ($row['config_type']) {
                'boolean' => (bool) $row['config_value'],
                'integer' => (int) $row['config_value'],
                'json' => json_decode($row['config_value'], true),
                default => $row['config_value'],
            };
        } catch (\Throwable $e) {
            return $default;
        }
    }
}
