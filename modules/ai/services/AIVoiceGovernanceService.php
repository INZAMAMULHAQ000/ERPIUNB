<?php
/**
 * AI F28 — Voice Governance Service.
 *
 * Enforces F24 governance on all voice commands.
 * Voice must never create a bypass of authentication, RBAC, or policies.
 *
 * Covers Section 23: F24 Governance for Voice.
 */
class AIVoiceGovernanceService
{
    /**
     * Enforce F24 governance on a voice command before execution.
     */
    public static function enforce(PDO $db, int $userId, int $companyId, string $text, array $context = []): array
    {
        $result = [
            'allowed' => true,
            'decision' => 'allow',
            'requires_confirmation' => false,
            'requires_review' => false,
            'risk_level' => 'low',
            'reason' => '',
            'checks' => [],
        ];

        // 1. Emergency kill switch check
        if (class_exists('AIEmergencyControlsService')) {
            if (AIEmergencyControlsService::isControlActive($db, 'disable_voice')) {
                return [
                    'allowed' => false,
                    'decision' => 'deny',
                    'reason' => 'Voice interface is disabled by emergency control',
                    'checks' => ['emergency' => false],
                ];
            }
        }

        // 2. Injection detection on transcribed text (Section 14)
        if (class_exists('AIInjectionDetectionService')) {
            $injectionCheck = AIInjectionDetectionService::analyze(
                $db,
                $text,
                AIInjectionDetectionService::TRUST_USER_INPUT,
                array_merge($context, ['source' => 'voice_transcription'])
            );

            $result['checks']['injection'] = [
                'passed' => $injectionCheck['safe'],
                'action' => $injectionCheck['action'],
            ];

            if ($injectionCheck['action'] === 'block') {
                $result['allowed'] = false;
                $result['decision'] = 'deny';
                $result['reason'] = 'Voice input blocked by security policy — potential injection detected';
                self::logInjectionAttempt($db, $userId, $companyId, $text, $injectionCheck);
                return $result;
            }

            if (!$injectionCheck['safe']) {
                $result['checks']['injection']['warning'] = 'Potential injection pattern detected but not blocked';
            }
        }

        // 3. Full governance enforcement via middleware
        if (class_exists('AIGovernanceMiddleware')) {
            $governanceResult = AIGovernanceMiddleware::enforce($db, 'voice', 'voice_command', array_merge($context, [
                'user_id' => $userId,
                'company_id' => $companyId,
                'message' => $text,
                'action_type' => self::detectActionType($text),
            ]));

            $result['checks']['governance'] = [
                'passed' => $governanceResult['allowed'],
                'decision' => $governanceResult['decision'],
            ];

            if (!$governanceResult['allowed']) {
                $result['allowed'] = false;
                $result['decision'] = $governanceResult['decision'];
                $result['reason'] = $governanceResult['reason'];
                return $result;
            }

            if ($governanceResult['requires_confirmation']) {
                $result['requires_confirmation'] = true;
            }
            if ($governanceResult['requires_review']) {
                $result['requires_review'] = true;
            }
        }

        // 4. Risk assessment
        $result['risk_level'] = self::assessRisk($text);
        if ($result['risk_level'] === 'critical') {
            $result['requires_confirmation'] = true;
        }

        return $result;
    }

    /**
     * Detect the action type from voice text.
     */
    private static function detectActionType(string $text): string
    {
        $lower = strtolower($text);

        if (preg_match('/\b(show|display|list|find|search|what|how many|report)\b/i', $lower)) {
            return 'read';
        }
        if (preg_match('/\b(create|add|new|prepare|draft|generate)\b/i', $lower)) {
            return 'write';
        }
        if (preg_match('/\b(delete|remove|cancel|void|terminate)\b/i', $lower)) {
            return 'delete';
        }
        if (preg_match('/\b(approve|confirm|authorize|accept|reject)\b/i', $lower)) {
            return 'review';
        }
        if (preg_match('/\b(send|email|publish|deploy|ship)\b/i', $lower)) {
            return 'external';
        }
        if (preg_match('/\b(open|go to|navigate|take me)\b/i', $lower)) {
            return 'navigate';
        }

        return 'unknown';
    }

    /**
     * Assess risk level of a voice command.
     */
    private static function assessRisk(string $text): string
    {
        $lower = strtolower($text);

        // Critical: financial actions, mass operations
        if (preg_match('/\b(approve|confirm|authorize)\s+(payment|invoice|order|requisition)\b/i', $lower)) return 'critical';
        if (preg_match('/\b(delete|remove|destroy)\s+(all|every|batch)\b/i', $lower)) return 'critical';
        if (preg_match('/\b(send|email|publish)\s+(to\s+)?\d+\s+(suppliers|vendors|customers|recipients)\b/i', $lower)) return 'critical';

        // High: write actions, external communications
        if (preg_match('/\b(delete|remove|cancel|void|terminate)\b/i', $lower)) return 'high';
        if (preg_match('/\b(send|email|publish|deploy|ship)\b/i', $lower)) return 'high';
        if (preg_match('/\b(create|add|new)\b.*\b(supplier|vendor|purchase|order|payment)\b/i', $lower)) return 'high';

        // Medium: modifications
        if (preg_match('/\b(update|modify|change|edit)\b/i', $lower)) return 'medium';
        if (preg_match('/\b(create|add|new)\b/i', $lower)) return 'medium';

        return 'low';
    }

    /**
     * Filter voice response for sensitive data (Section 16).
     * Ensures no passwords, API keys, tokens, or restricted data is spoken.
     */
    public static function filterResponse(PDO $db, int $userId, int $companyId, string $responseText): string
    {
        // Redact secrets
        if (class_exists('AISecretProtectionService')) {
            $responseText = AISecretProtectionService::redactSecrets($responseText);
        }

        // Apply F24 data masking
        if (class_exists('AIDataClassificationService')) {
            $classification = AIDataClassificationService::classifyContent($responseText);
            if ($classification['masking_required']) {
                $responseText = AIDataClassificationService::maskData($responseText, $classification['classification']);
            }
        }

        return $responseText;
    }

    /**
     * Log injection attempt.
     */
    private static function logInjectionAttempt(PDO $db, int $userId, int $companyId, string $text, array $analysis): void
    {
        try {
            $db->prepare("
                INSERT INTO ai_voice_injection_log
                    (company_id, user_id, transcription_text, injection_type, confidence, action_taken)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([
                $companyId,
                $userId,
                substr($text, 0, 2000),
                $analysis['injections_detected'][0]['type'] ?? 'unknown',
                $analysis['max_confidence'] ?? 0,
                $analysis['action'] === 'block' ? 'blocked' : 'warned',
            ]);

            // F23: Record observability trace
            if (class_exists('AIIntelligenceService')) {
                AIIntelligenceService::recordObservabilityTrace($db, 'voice_injection_detected', [
                    'user_id' => $userId,
                    'injection_type' => $analysis['injections_detected'][0]['type'] ?? 'unknown',
                    'action' => $analysis['action'],
                ]);
            }
        } catch (\Throwable $e) {
            // Non-critical
        }
    }
}
