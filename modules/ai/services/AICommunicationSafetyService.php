<?php
/**
 * AI F32 — Communication Safety Service
 * 
 * Sending safety, recipient validation, attachment safety, sensitive data protection.
 * Sections 16-19, 25-26 of F32 specification.
 */

class AICommunicationSafetyService {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Validate a draft before sending — comprehensive safety check.
     *
     * @param array $draft Draft data
     * @param array $attachments Selected attachments
     * @return array Safety result with pre-send summary
     */
    public function validateBeforeSending($draft, $attachments = []) {
        $result = [
            'safe' => true,
            'warnings' => [],
            'errors' => [],
            'risk_level' => 'low',
            'approval_required' => false,
            'summary' => [],
        ];

        // 1. Recipient validation
        $recipientCheck = $this->validateRecipient($draft);
        $result['warnings'] = array_merge($result['warnings'], $recipientCheck['warnings']);
        $result['errors'] = array_merge($result['errors'], $recipientCheck['errors']);

        // 2. Content safety
        $contentCheck = $this->validateContent($draft);
        $result['warnings'] = array_merge($result['warnings'], $contentCheck['warnings']);
        $result['errors'] = array_merge($result['errors'], $contentCheck['errors']);

        // 3. Attachment safety
        $attachmentCheck = $this->validateAttachments($attachments);
        $result['warnings'] = array_merge($result['warnings'], $attachmentCheck['warnings']);
        $result['errors'] = array_merge($result['errors'], $attachmentCheck['errors']);

        // 4. Sensitive data protection
        $sensitiveCheck = $this->checkSensitiveData($draft);
        $result['warnings'] = array_merge($result['warnings'], $sensitiveCheck['warnings']);
        $result['blocked'] = $sensitiveCheck['blocked'] ?? [];

        // 5. Risk classification
        $result['risk_level'] = $this->classifyRisk($draft, $attachments);

        // 6. Quality checks
        $qualityCheck = $this->runQualityChecks($draft);
        $result['warnings'] = array_merge($result['warnings'], $qualityCheck['warnings']);

        // 7. Approval requirements
        $result['approval_required'] = $this->requiresApproval($result['risk_level'], $draft);

        // 8. Emergency controls
        $emergencyCheck = $this->checkEmergencyControls();
        if ($emergencyCheck['sending_disabled']) {
            $result['errors'][] = 'External communication is currently disabled by emergency controls';
        }

        // 9. Build pre-send summary
        $result['summary'] = [
            'To'           => $draft['recipient'] ?? 'Not specified',
            'Cc'           => $draft['cc'] ?? '',
            'Subject'      => $draft['subject'] ?? '',
            'Attachments'  => count($attachments),
            'Data included'=> $this->listDataIncluded($draft),
            'Risk level'   => $result['risk_level'],
            'Approval required' => $result['approval_required'],
        ];

        // 10. If any errors, mark as unsafe
        if (!empty($result['errors'])) {
            $result['safe'] = false;
        }

        return $result;
    }

    /**
     * Validate a recipient against authorized communication channels.
     */
    public function validateRecipient($draft) {
        $result = ['warnings' => [], 'errors' => [], 'valid' => true];

        $recipient = $draft['recipient'] ?? '';
        $recipientType = $draft['recipient_type'] ?? '';

        if (empty($recipient)) {
            $result['errors'][] = 'No recipient specified';
            $result['valid'] = false;
            return $result;
        }

        // Email validation
        if (strpos($recipient, '@') !== false) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $result['errors'][] = 'Invalid email address format';
                $result['valid'] = false;
            }

            // Check for external domains
            $domain = substr($recipient, strpos($recipient, '@') + 1);
            $internalDomains = $this->getInternalDomains();
            if (!in_array(strtolower($domain), $internalDomains)) {
                $result['warnings'][] = "External recipient: {$recipient}";
            }

            // Check against tenant contacts
            if (!$this->isKnownContact($recipient, $recipientType)) {
                $result['warnings'][] = 'Recipient is not a known contact in this company';
            }
        }

        // Check tenant/company ownership
        if ($recipientType === 'customer' && $draft['context_id']) {
            if (!$this->isCompanyRecord('customers', $draft['context_id'])) {
                $result['errors'][] = 'Recipient belongs to a different company';
                $result['valid'] = false;
            }
        }

        if ($recipientType === 'supplier' && $draft['context_id']) {
            if (!$this->isCompanyRecord('suppliers', $draft['context_id'])) {
                $result['errors'][] = 'Supplier belongs to a different company';
                $result['valid'] = false;
            }
        }

        return $result;
    }

    /**
     * Validate content for safety issues.
     */
    public function validateContent($draft) {
        $result = ['warnings' => [], 'errors' => []];
        $body = $draft['body'] ?? '';

        // Check for unsupported claims
        if (preg_match('/\b(guarantee|promise|warrant|commit)\b/i', $body)) {
            $result['warnings'][] = 'Message contains commitment language — review before sending';
        }

        // Check for financial commitments
        if (preg_match('/\b(refund|credit|reimburse|pay\s+back)\b/i', $body)) {
            $result['warnings'][] = 'Message contains financial commitment language';
        }

        // Check for contract/legal language
        if (preg_match('/\b(contract|agreement|terms\s+and\s+conditions|binding|legal)\b/i', $body)) {
            $result['warnings'][] = 'Message contains legal/contractual language';
        }

        // Check for accidental sensitive information
        if (preg_match('/\b(password|api[_\s]key|secret|token|private\s+key)\b/i', $body)) {
            $result['errors'][] = 'Message may contain sensitive information — review required';
        }

        // Check for missing placeholders
        if (preg_match_all('/\[([^\]]+)\]/', $body, $m)) {
            foreach ($m[1] as $placeholder) {
                $result['warnings'][] = "Unresolved placeholder: [{$placeholder}]";
            }
        }

        // Check for inappropriate tone markers
        if (preg_match('/\b(angry|furious|outraged|demand)\b/i', $body)) {
            $result['warnings'][] = 'Message tone may be inappropriate for business communication';
        }

        return $result;
    }

    /**
     * Validate attachments against F27 permissions and safety.
     */
    public function validateAttachments($attachments) {
        $result = ['warnings' => [], 'errors' => [], 'valid_attachments' => []];

        if (empty($attachments)) return $result;

        foreach ($attachments as $attachment) {
            $docId = $attachment['id'] ?? $attachment;

            // Check F27 document permissions
            if (class_exists('AIDocumentSecurityService')) {
                $secService = new AIDocumentSecurityService($this->companyId, $this->userId);
                if (method_exists($secService, 'canAccess')) {
                    if (!$secService->canAccess($docId)) {
                        $result['errors'][] = "Unauthorized access to document #{$docId}";
                        continue;
                    }
                }
            }

            // Check file type safety
            $filename = $attachment['filename'] ?? '';
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $dangerous = ['exe', 'bat', 'cmd', 'com', 'msi', 'scr', 'pif', 'vbs', 'js', 'ws', 'wsf', 'ps1', 'sh', 'php', 'php3', 'php4', 'php5'];

            if (in_array($ext, $dangerous)) {
                $result['errors'][] = "Dangerous file type blocked: {$filename}";
                continue;
            }

            $result['valid_attachments'][] = $attachment;
        }

        return $result;
    }

    /**
     * Check for sensitive data that should not be sent externally.
     */
    public function checkSensitiveData($draft) {
        $result = ['warnings' => [], 'blocked' => []];
        $body = $draft['body'] ?? '';
        $subject = $draft['subject'] ?? '';
        $combined = $body . ' ' . $subject;

        // Apply F24 data classification
        if (class_exists('AIDataClassificationService')) {
            $classService = new AIDataClassificationService();
            if (method_exists($classService, 'classifyText')) {
                $classification = $classService->classifyText($combined);
                if ($classification === 'restricted') {
                    $result['warnings'][] = 'Content classified as restricted — may require masking before external send';
                }
            }
        }

        // Check for secrets/credentials
        if (class_exists('AISecretProtectionService')) {
            $secretService = new AISecretProtectionService();
            if (method_exists($secretService, 'detectSecrets')) {
                $secrets = $secretService->detectSecrets($combined);
                if (!empty($secrets)) {
                    $result['blocked'] = $secrets;
                    $result['warnings'][] = 'Sensitive information detected — must be removed before sending';
                }
            }
        }

        // Check for API keys patterns
        if (preg_match('/(?:sk-|ak_|pk_|rk_)[a-zA-Z0-9]{20,}/', $combined)) {
            $result['blocked'][] = 'API key pattern detected';
            $result['warnings'][] = 'API key detected in message — must be removed';
        }

        // Check for password patterns
        if (preg_match('/(?:password|passwd|pwd)\s*[:=]\s*\S+/i', $combined)) {
            $result['blocked'][] = 'Password detected';
            $result['warnings'][] = 'Password detected in message — must be removed';
        }

        return $result;
    }

    /**
     * Classify outgoing communication risk level.
     *
     * @return string low|medium|high|critical
     */
    public function classifyRisk($draft, $attachments = []) {
        $body = strtolower($draft['body'] ?? '');
        $subject = strtolower($draft['subject'] ?? '');
        $combined = $body . ' ' . $subject;

        $riskScore = 0;

        // External recipient
        if (!empty($draft['recipient']) && filter_var($draft['recipient'], FILTER_VALIDATE_EMAIL)) {
            $riskScore += 1;
        }

        // Financial content
        if (preg_match('/\b(invoice|payment|amount|price|total|cost|budget|financial)\b/', $combined)) {
            $riskScore += 1;
        }

        // Contractual language
        if (preg_match('/\b(contract|agreement|terms|binding|legal|warranty)\b/', $combined)) {
            $riskScore += 2;
        }

        // Sensitive data
        if (preg_match('/\b(confidential|restricted|sensitive|secret|private)\b/', $combined)) {
            $riskScore += 1;
        }

        // Bulk
        if (($draft['recipient_count'] ?? 1) > 10) {
            $riskScore += 2;
        } elseif (($draft['recipient_count'] ?? 1) > 1) {
            $riskScore += 1;
        }

        // Legal content
        if (preg_match('/\b(termination|lawsuit|dispute|complaint|breach)\b/', $combined)) {
            $riskScore += 2;
        }

        // Irreversible commitment
        if (preg_match('/\b(irreversible|non.refundable|final sale|no returns)\b/', $combined)) {
            $riskScore += 2;
        }

        // Map score to level
        if ($riskScore >= 5) return 'critical';
        if ($riskScore >= 3) return 'high';
        if ($riskScore >= 1) return 'medium';
        return 'low';
    }

    /**
     * Run quality checks before sending.
     */
    public function runQualityChecks($draft) {
        $result = ['warnings' => []];
        $body = $draft['body'] ?? '';

        // Missing recipient
        if (empty($draft['recipient'])) {
            $result['warnings'][] = 'Missing recipient';
        }

        // Missing subject
        if (empty($draft['subject']) && ($draft['communication_type'] ?? '') === 'email') {
            $result['warnings'][] = 'Missing email subject';
        }

        // Unresolved template variables
        if (preg_match_all('/\{\{(\w+)\}\}/', $body, $m)) {
            foreach ($m[1] as $var) {
                $result['warnings'][] = "Unresolved template variable: {{$var}}";
            }
        }

        // Missing attachments that were expected
        if (preg_match('/\b(please\s+find\s+attached|see\s+attached|attached\s+(?:is|find))\b/i', $body)) {
            if (empty($draft['attachments'])) {
                $result['warnings'][] = 'Message mentions attachments but none selected';
            }
        }

        // Inconsistent dates (if multiple dates mentioned)
        if (preg_match_all('/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/', $body, $m)) {
            if (count($m[0]) > 3) {
                $result['warnings'][] = 'Multiple dates mentioned — verify consistency';
            }
        }

        // Empty body
        if (strlen(trim($body)) < 10) {
            $result['warnings'][] = 'Message body is very short';
        }

        return $result;
    }

    /**
     * Check if approval is required based on risk and policy.
     */
    public function requiresApproval($riskLevel, $draft) {
        // Critical always requires approval
        if ($riskLevel === 'critical') return true;

        // High risk requires approval
        if ($riskLevel === 'high') return true;

        // Bulk communications require approval
        if (($draft['recipient_count'] ?? 1) > 5) return true;

        // Check F24 policy
        if (class_exists('AIPolicyEngine')) {
            $policy = new AIPolicyEngine();
            if (method_exists($policy, 'requiresApproval')) {
                return $policy->requiresApproval('communication_send', [
                    'risk_level' => $riskLevel,
                    'recipient_type' => $draft['recipient_type'] ?? 'external',
                    'is_bulk' => ($draft['recipient_count'] ?? 1) > 1,
                ]);
            }
        }

        return false;
    }

    /**
     * Create a confirmation token for sending (F24 parameter binding).
     */
    public function createSendConfirmation($draftId, $draft) {
        $db = $this->getDb();
        if (!$db) return null;

        $token = bin2hex(random_bytes(32));
        $paramsHash = hash('sha256', json_encode([
            'draft_id'    => $draftId,
            'recipient'   => $draft['recipient'] ?? '',
            'subject'     => $draft['subject'] ?? '',
            'body_hash'   => hash('sha256', $draft['body'] ?? ''),
        ]));

        $sql = "INSERT INTO ai_communication_actions
                (company_id, user_id, action_key, action_type, draft_id, status, risk_level,
                 approval_required, confirmation_token, confirmation_parameters_hash)
                VALUES (?, ?, ?, 'send', ?, 'pending', ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        if (!$stmt) return null;

        $risk = $this->classifyRisk($draft);
        $approval = $this->requiresApproval($risk, $draft);

        $stmt->bind_param('iisissis',
            $this->companyId, $this->userId, $token,
            $draftId, $risk, $approval ? 1 : 0, $token, $paramsHash
        );

        $stmt->execute();
        $actionId = $stmt->insert_id;
        $stmt->close();

        return [
            'action_id' => $actionId,
            'token' => $token,
            'parameters_hash' => $paramsHash,
            'approval_required' => $approval,
            'risk_level' => $risk,
        ];
    }

    /**
     * Confirm and send a communication.
     */
    public function confirmAndSend($token, $confirmParams) {
        $db = $this->getDb();
        if (!$db) return ['error' => 'Database not available'];

        // 1. Load the action
        $sql = "SELECT * FROM ai_communication_actions WHERE confirmation_token = ? AND company_id = ? AND status = 'pending'";
        $stmt = $db->prepare($sql);
        if (!$stmt) return ['error' => 'Query failed'];

        $stmt->bind_param('si', $token, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $action = $result->fetch_assoc();
        $stmt->close();

        if (!$action) return ['error' => 'Invalid or expired confirmation'];

        // 2. Verify parameters hash hasn't changed
        $currentHash = hash('sha256', json_encode($confirmParams));
        if ($currentHash !== $action['confirmation_parameters_hash']) {
            // Parameters changed — invalidate confirmation
            $this->updateActionStatus($action['id'], 'cancelled');
            return ['error' => 'Parameters have changed — confirmation invalidated. Please review and confirm again.'];
        }

        // 3. Check approval
        if ($action['approval_required'] && !$action['approved_by']) {
            return ['error' => 'Approval required before sending'];
        }

        // 4. Load the draft
        $draftService = new AICommunicationDraftService($this->companyId, $this->userId);
        $draft = $draftService->getDraft($action['draft_id']);
        if (!$draft) return ['error' => 'Draft not found'];

        // 5. Final safety validation
        $safetyCheck = $this->validateBeforeSending($draft);
        if (!$safetyCheck['safe']) {
            return ['error' => 'Safety check failed', 'issues' => $safetyCheck['errors']];
        }

        // 6. Send through ERP communication service
        $sendResult = $this->sendViaErpService($draft);

        // 7. Update status
        if ($sendResult['success']) {
            $this->updateActionStatus($action['id'], 'sent');
            $this->updateDraftStatus($action['draft_id'], 'sent', $sendResult['message_id'] ?? null);

            $this->recordEvent('send_success', $action['id'], [
                'draft_id' => $action['draft_id'],
                'recipient' => $draft['recipient'],
            ]);
        } else {
            $this->updateActionStatus($action['id'], 'failed');
            $this->recordEvent('send_failed', $action['id'], [
                'draft_id' => $action['draft_id'],
                'error' => $sendResult['error'] ?? 'Unknown error',
            ]);
        }

        return $sendResult;
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    private function getInternalDomains() {
        $db = $this->getDb();
        if (!$db) return [];

        $sql = "SELECT DISTINCT domain FROM company_domains WHERE company_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return [];

        $stmt->bind_param('i', $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $domains = [];
        while ($row = $result->fetch_assoc()) {
            $domains[] = strtolower($row['domain']);
        }
        $stmt->close();

        // Fallback: use configured domain
        if (empty($domains)) {
            $domains[] = 'localhost';
        }

        return $domains;
    }

    private function isKnownContact($email, $type) {
        $db = $this->getDb();
        if (!$db) return false;

        $tableMap = [
            'customer' => 'customers',
            'supplier' => 'suppliers',
            'employee' => 'employees',
        ];

        $table = $tableMap[$type] ?? null;
        if (!$table) return true; // Unknown type, allow

        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE email = ? AND company_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('si', $email, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ($row['cnt'] ?? 0) > 0;
    }

    private function isCompanyRecord($table, $id) {
        $db = $this->getDb();
        if (!$db) return false;

        $sql = "SELECT COUNT(*) as cnt FROM {$table} WHERE id = ? AND company_id = ?";
        $stmt = $db->prepare($sql);
        if (!$stmt) return false;

        $stmt->bind_param('ii', $id, $this->companyId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        return ($row['cnt'] ?? 0) > 0;
    }

    private function listDataIncluded($draft) {
        $data = [];
        if (!empty($draft['subject'])) $data[] = 'Subject';
        if (!empty($draft['body'])) $data[] = 'Message body';
        if (!empty($draft['recipient'])) $data[] = 'Recipient info';
        if (!empty($draft['verified_facts'])) {
            $facts = json_decode($draft['verified_facts'], true);
            $data[] = count($facts) . ' ERP facts';
        }
        return implode(', ', $data);
    }

    private function checkEmergencyControls() {
        if (class_exists('AIEmergencyControlsService')) {
            if (method_exists('AIEmergencyControlsService', 'isExternalCommDisabled')) {
                return ['sending_disabled' => AIEmergencyControlsService::isExternalCommDisabled()];
            }
            if (method_exists('AIEmergencyControlsService', 'isAiDisabled')) {
                return ['sending_disabled' => AIEmergencyControlsService::isAiDisabled()];
            }
        }
        return ['sending_disabled' => false];
    }

    private function sendViaErpService($draft) {
        // Try to use existing ERP communication infrastructure
        if (class_exists('AIExternalCommService')) {
            $commService = new AIExternalCommService($this->companyId, $this->userId);
            if (method_exists($commService, 'sendEmail')) {
                return $commService->sendEmail([
                    'to'      => $draft['recipient'],
                    'cc'      => $draft['cc'] ?? '',
                    'subject' => $draft['subject'],
                    'body'    => $draft['body'],
                ]);
            }
        }

        // Fallback: log the send attempt
        return [
            'success' => false,
            'error' => 'ERP communication service not available',
        ];
    }

    private function updateActionStatus($actionId, $status, $extra = '') {
        $db = $this->getDb();
        if (!$db) return;

        $sql = "UPDATE ai_communication_actions SET status = ?, updated_at = NOW()";
        $params = [$status];
        $types = 's';

        if ($status === 'sent') {
            $sql .= ", sent_at = NOW()";
        }

        $sql .= " WHERE id = ?";
        $params[] = $actionId;
        $types .= 'i';

        $stmt = $db->prepare($sql);
        if (!$stmt) return;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    private function updateDraftStatus($draftId, $status, $externalId = null) {
        $db = $this->getDb();
        if (!$db) return;

        $sql = "UPDATE ai_communication_drafts SET status = ?, send_status = ?, sent_at = NOW()";
        $params = [$status, $status];
        $types = 'ss';

        if ($externalId) {
            $sql .= ", external_message_id = ?";
            $params[] = $externalId;
            $types .= 's';
        }

        $sql .= " WHERE id = ? AND company_id = ?";
        $params[] = $draftId;
        $params[] = $this->companyId;
        $types .= 'ii';

        $stmt = $db->prepare($sql);
        if (!$stmt) return;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
    }

    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;
        $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'action', ?, ?, ?)";
        $stmt = $db->prepare($sql);
        if (!$stmt) return;
        $stmt->bind_param('isisi', $this->companyId, $eventType, $entityId, $this->userId, json_encode($metadata));
        $stmt->execute();
        $stmt->close();
    }

    private function getDb() {
        if (function_exists('getDB')) return getDB();
        global $db;
        return $db ?? null;
    }
}
