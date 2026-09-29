<?php
/**
 * AI F21: Voice Interface — Core Voice Service
 * Handles audio upload, validation, transcription, command processing
 */

class AIVoiceService {
    
    public $db;
    public $companyId;
    public $userId;
    public $sttService;
    public $ttsService;
    public $skipPermissions;
    
    public function __construct($db, $companyId, $userId, $skipPermissions = false) {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->skipPermissions = $skipPermissions;
        $this->sttService = new AIVoiceSTTProvider($db, $companyId);
        $this->ttsService = new AIVoiceTTSProvider($db, $companyId, $userId);
    }
    
    // === AUDIO UPLOAD & VALIDATION ===
    
    /**
     * Process uploaded audio file
     */
    public function processAudio($audioData, $mimeType, $durationMs = null, $language = null) {
        // Validate audio
        $fileSize = strlen($audioData);
        $validation = $this->sttService->validateAudio($mimeType, $fileSize, $durationMs);
        if (!$validation['valid']) {
            return ['success' => false, 'errors' => $validation['errors']];
        }

        // Check voice feature enabled
        if (!$this->isFeatureEnabled('voice_input')) {
            return ['success' => false, 'error' => 'Voice input is currently disabled.'];
        }
        if (!$this->isFeatureEnabled('stt')) {
            return ['success' => false, 'error' => 'Speech-to-text is currently disabled.'];
        }
        
        // Check permissions
        if (!$this->hasPermission('ai.voice.use')) {
            return ['success' => false, 'error' => 'You do not have permission to use voice features.'];
        }
        
        // Check usage limits
        $limitCheck = $this->checkUsageLimits('stt');
        if (!$limitCheck['allowed']) {
            return ['success' => false, 'error' => $limitCheck['message']];
        }
        
        // Create transcription record
        $transcription = $this->sttService->createTranscription(
            $this->userId, $mimeType, $fileSize, $durationMs, $language
        );
        
        // Save audio file temporarily
        $uploadDir = __DIR__ . '/../../uploads/voice_audio/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $audioPath = $uploadDir . $transcription['id'] . '_' . time() . '.webm';
        file_put_contents($audioPath, $audioData);
        
        // Audit log
        $this->auditLog('recording_uploaded', $transcription['id'], [
            'duration_ms' => $durationMs,
            'file_size' => $fileSize,
            'mime_type' => $mimeType
        ]);
        
        // Perform transcription
        $result = $this->performTranscription($transcription['id'], $audioPath, $language);
        
        // Schedule audio cleanup
        $this->scheduleAudioCleanup($audioPath);
        
        return $result;
    }
    
    /**
     * Perform the actual transcription
     */
    private function performTranscription($transcriptionId, $audioPath, $language = null) {
        $provider = $this->sttService->resolveProvider();
        
        // Update status
        $this->db->prepare("UPDATE ai_voice_transcriptions SET status = 'processing' WHERE id = ?")
            ->execute([$transcriptionId]);
        
        // Browser-based STT: the actual transcription is done client-side via Web Speech API
        // Server receives the text result after client-side processing
        if ($provider['provider'] === 'browser') {
            return [
                'success' => true,
                'transcription_id' => $transcriptionId,
                'status' => 'pending_client_transcription',
                'message' => 'Audio received. Processing transcription...'
            ];
        }
        
        // Future: Server-side STT (OpenAI Whisper, etc.)
        return [
            'success' => true,
            'transcription_id' => $transcriptionId,
            'status' => 'processing'
        ];
    }
    
    /**
     * Submit client-side transcription result
     */
    public function submitTranscriptionResult($transcriptionId, $text, $confidence = null, $detectedLanguage = null) {
        if (!$this->hasPermission('ai.voice.transcribe')) {
            return ['success' => false, 'error' => 'No transcription permission.'];
        }

        $result = $this->sttService->completeTranscription(
            $transcriptionId, $text, $confidence, $detectedLanguage,
            ['source' => 'client_browser', 'method' => 'web_speech_api']
        );

        if ($result['success']) {
            $transcription = $this->sttService->getTranscription($transcriptionId);
            $durationMs = (int)($transcription['duration_ms'] ?? 0);
            $provider = $transcription['provider'] ?? 'browser';
            $this->trackUsage('stt', $provider, $durationMs, $this->estimateCost('stt', $provider, $durationMs, $text));
            $this->auditLog('transcription_completed', $transcriptionId, [
                'text_length' => strlen($text),
                'confidence' => $confidence,
                'language' => $detectedLanguage
            ]);
        }
        
        return array_merge($result, [
            'transcription_id' => $transcriptionId,
            'text' => $text,
            'preview' => $text
        ]);
    }
    
    // === VOICE COMMAND PROCESSING ===
    
    /**
     * Process a voice command through the normal AI pipeline
     */
    public function processVoiceCommand($transcriptionId, $text, $context = null) {
        if (!$this->hasPermission('ai.voice.use')) {
            return ['success' => false, 'error' => 'No voice permission.'];
        }
        if (!$this->isFeatureEnabled('voice_commands')) {
            return ['success' => false, 'error' => 'Voice commands are currently disabled.'];
        }
        
        // Check for voice shortcuts first (before transcription validation)
        $shortcut = $this->matchShortcut($text);
        
        // Get the transcription (optional for shortcuts)
        $transcription = $transcriptionId > 0 ? $this->sttService->getTranscription($transcriptionId) : null;
        
        if ($shortcut) {
            return [
                'success' => true,
                'type' => 'shortcut',
                'action' => $shortcut['target_action'],
                'url' => function_exists('resolveAppUrl') ? resolveAppUrl($shortcut['target_url']) : $shortcut['target_url'],
                'description' => $shortcut['description']
            ];
        }

        $navigationIntent = $this->isNavigationRequest($text);
        if (!empty($navigationIntent['is_navigation']) && class_exists('AINavigationService')) {
            $navigation = AINavigationService::findNavigation($this->db, $navigationIntent['target'] ?? $text);
            if (!empty($navigation['route'])) {
                return [
                    'success' => true,
                    'type' => 'navigation',
                    'title' => $navigation['label'] ?? 'Navigation',
                    'message' => 'Opening ' . ($navigation['label'] ?? 'the requested page') . '.',
                    'route' => function_exists('resolveAppUrl') ? resolveAppUrl($navigation['route']) : $navigation['route'],
                    'label' => $navigation['label'] ?? 'Open',
                    'source' => 'voice',
                ];
            }
        }

        // Check for high-risk action keywords
        $riskLevel = $this->assessVoiceCommandRisk($text);
        
        // Audit log
        $this->auditLog('voice_command_sent', $transcriptionId, [
            'text' => $text,
            'risk_level' => $riskLevel,
            'context' => $context
        ]);

        if (class_exists('AICommandCenterService')) {
            try {
                $commandContext = is_array($context) ? $context : [];
                $commandContext['voice'] = true;
                $commandContext['source'] = 'voice';
                $commandContext['transcription_id'] = $transcriptionId;
                $commandContext['risk_level'] = $riskLevel;

                $result = AICommandCenterService::processCommand(
                    $this->db,
                    $this->userId,
                    $text,
                    null,
                    $commandContext
                );

                if (!empty($result['success'])) {
                    if (($result['type'] ?? '') === 'navigation' && !empty($result['route']) && function_exists('resolveAppUrl')) {
                        $result['route'] = resolveAppUrl($result['route']);
                    }
                    return $result;
                }
            } catch (\Throwable $e) {
                // Fall through to the legacy AI command response below.
            }
        }
        
        // Return for AI pipeline processing
        return [
            'success' => true,
            'type' => 'ai_command',
            'text' => $text,
            'transcription_id' => $transcriptionId,
            'risk_level' => $riskLevel,
            'context' => $context,
            'message' => $riskLevel === 'high' || $riskLevel === 'restricted' 
                ? 'This command involves a sensitive action. Please review and confirm on screen.'
                : null
        ];
    }
    
    // === VOICE NAVIGATION ===
    
    /**
     * Check if voice command is a navigation request
     */
    public function isNavigationRequest($text) {
        $navigationPatterns = [
            '/^open\s+(.+)/i',
            '/^go\s+to\s+(.+)/i',
            '/^take\s+me\s+to\s+(.+)/i',
            '/^navigate\s+to\s+(.+)/i',
            '/^show\s+(me\s+)?(.+)/i'
        ];
        
        foreach ($navigationPatterns as $pattern) {
            if (preg_match($pattern, trim($text), $matches)) {
                $target = end($matches);
                return [
                    'is_navigation' => true,
                    'target' => trim($target),
                    'text' => $text
                ];
            }
        }
        
        return ['is_navigation' => false];
    }
    
    // === TEXT-TO-SPEECH ===
    
    /**
     * Generate TTS for response
     */
    public function generateTTS($text, $language = null, $voice = null, $speed = 1.0) {
        if (!$this->hasPermission('ai.voice.playback')) {
            return ['success' => false, 'error' => 'No TTS permission.'];
        }
        if (!$this->isFeatureEnabled('tts')) {
            return ['success' => false, 'error' => 'Text-to-speech is currently disabled.'];
        }
        
        $limitCheck = $this->checkUsageLimits('tts');
        if (!$limitCheck['allowed']) {
            return ['success' => false, 'error' => $limitCheck['message']];
        }
        
        $result = $this->ttsService->generate($text, $this->userId, $language, $voice, $speed);

        if ($result['success']) {
            $estimatedCost = $this->estimateCost('tts', $result['provider'] ?? 'browser', 0, $text);
            $this->auditLog('tts_generated', null, [
                'text_length' => strlen($text),
                'language' => $language,
                'provider' => $result['provider'] ?? 'browser'
            ]);
            
            // Track usage
            $this->trackUsage('tts', $result['provider'] ?? 'browser', 0, $estimatedCost);
        }

        return $result;
    }
    
    // === DICTATION ===
    
    /**
     * Process dictation input
     */
    public function processDictation($text, $targetField = null) {
        if (!$this->hasPermission('ai.voice.dictate')) {
            return ['success' => false, 'error' => 'No dictation permission.'];
        }
        if (!$this->isFeatureEnabled('dictation')) {
            return ['success' => false, 'error' => 'Voice dictation is currently disabled.'];
        }
        
        // Check for sensitive fields
        $sensitiveFields = ['password', 'ssn', 'credit_card', 'bank_account', 'secret', 'token'];
        if ($targetField && in_array(strtolower($targetField), $sensitiveFields)) {
            return ['success' => false, 'error' => 'Dictation is not allowed for this field type.'];
        }
        
        $this->auditLog('dictation_used', null, [
            'text_length' => strlen($text),
            'target_field' => $targetField
        ]);
        
        return [
            'success' => true,
            'text' => $text,
            'target_field' => $targetField,
            'requires_accept' => true
        ];
    }
    
    // === VOICE SHORTCUTS ===
    
    /**
     * Match voice text against registered shortcuts
     */
    private function matchShortcut($text) {
        $normalized = strtolower(trim($text));
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_shortcuts 
             WHERE company_id = ? AND is_active = 1"
        );
        $stmt->execute([$this->companyId]);
        $shortcuts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($shortcuts as $shortcut) {
            if (strtolower($shortcut['command']) === $normalized) {
                return $shortcut;
            }
        }
        
        return null;
    }
    
    /**
     * Create/update voice shortcut
     */
    public function setShortcut($command, $targetAction, $targetUrl = null, $description = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_shortcuts (company_id, command, target_action, target_url, description, created_by)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE target_action = VALUES(target_action), target_url = VALUES(target_url), description = VALUES(description)"
        );
        $stmt->execute([$this->companyId, strtolower(trim($command)), $targetAction, $targetUrl, $description, $this->userId]);
        
        $this->auditLog('shortcuts_configured', null, ['command' => $command]);
        
        return ['success' => true];
    }
    
    /**
     * List shortcuts
     */
    public function listShortcuts() {
        $stmt = $this->db->prepare(
            "SELECT id, command, target_action, target_url, description, is_active, created_at
             FROM ai_voice_shortcuts WHERE company_id = ? ORDER BY command"
        );
        $stmt->execute([$this->companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Delete shortcut
     */
    public function deleteShortcut($shortcutId) {
        $stmt = $this->db->prepare(
            "DELETE FROM ai_voice_shortcuts WHERE id = ? AND company_id = ?"
        );
        $stmt->execute([$shortcutId, $this->companyId]);
        return ['success' => $stmt->rowCount() > 0];
    }
    
    // === RISK ASSESSMENT ===
    
    /**
     * Assess risk level of a voice command
     */
    private function assessVoiceCommandRisk($text) {
        $lower = strtolower($text);
        
        $highRiskPatterns = [
            '/\b(delete|remove|destroy|terminate)\b/i',
            '/\b(approve|confirm|authorize|accept)\s+(payment|order|requisition|invoice)\b/i',
            '/\b(publish|deploy|ship|send)\b/i'
        ];
        
        $mediumRiskPatterns = [
            '/\b(create|add|new)\b.*\b(supplier|vendor|purchase|order|requisition)\b/i',
            '/\b(update|modify|change|edit)\b.*\b(price|amount|quantity|date)\b/i'
        ];
        
        foreach ($highRiskPatterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'high';
            }
        }
        
        foreach ($mediumRiskPatterns as $pattern) {
            if (preg_match($pattern, $lower)) {
                return 'medium';
            }
        }
        
        return 'low';
    }
    
    // === ERP PAGE CONTEXT ===
    
    /**
     * Build page context for voice requests
     */
    public function buildPageContext($module, $page, $recordId = null, $recordType = null) {
        return [
            'module' => $module,
            'page' => $page,
            'record_id' => $recordId,
            'record_type' => $recordType,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    // === CONVERSATION CONTEXT ===
    
    /**
     * Get conversation context for follow-up voice requests
     */
    public function getConversationContext($conversationId = null) {
        if (!$conversationId) return null;
        
        // Reuse existing conversation context from AI ConversationService
        if (class_exists('AIConversationService')) {
            // Get recent messages for context
            return ['conversation_id' => $conversationId, 'type' => 'follow_up'];
        }
        
        return null;
    }
    
    // === PERMISSIONS & FEATURES ===
    
    /**
     * Check if voice feature is enabled
     */
    public function isFeatureEnabled($feature) {
        $featureMap = [
            'voice_input' => ['voice_input', 'disable_voice_input'],
            'stt' => ['speech_to_text', 'stt', 'disable_speech_to_text', 'disable_stt'],
            'tts' => ['text_to_speech', 'tts', 'disable_text_to_speech', 'disable_tts'],
            'dictation' => ['voice_dictation', 'dictation', 'disable_voice_dictation', 'disable_dictation'],
            'voice_commands' => ['voice_commands', 'disable_voice_commands']
        ];

        if (class_exists('AIKillSwitchService')) {
            foreach ($featureMap[$feature] ?? [] as $switchKey) {
                if (!AIKillSwitchService::isEnabled($this->db, $switchKey)) {
                    return false;
                }
            }
        }

        // Backward-compatible fallback to central config.
        $configPath = __DIR__ . '/../config/ai_central_config.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            $killSwitches = $config['voice']['kill_switches'] ?? [];
            foreach ($featureMap[$feature] ?? [] as $switchKey) {
                if (!empty($killSwitches[$switchKey])) {
                    return false;
                }
            }
        }

        return true;
    }
    
    /**
     * Check permission
     */
    private function hasPermission($permission) {
        if ($this->skipPermissions) return true;
        // Check if session user is set
        if (empty($_SESSION['user_id'])) return false;
        if (function_exists('hasPermission')) {
            return hasPermission($permission);
        }
        return true;
    }
    
    // === USAGE LIMITS ===
    
    /**
     * Check usage limits for feature
     */
    public function checkUsageLimits($feature) {
        $limits = $this->getCurrentLimits();

        $stmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_requests,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE) THEN 1 ELSE 0 END) as minute_requests,
                COALESCE(SUM(CASE WHEN DATE_FORMAT(created_at, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m') THEN estimated_cost ELSE 0 END), 0) as month_cost
             FROM ai_voice_usage
             WHERE company_id = ? AND user_id = ? AND feature = ?"
        );
        $stmt->execute([$this->companyId, $this->userId, $feature]);
        $usage = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $minuteLimit = (int)($limits['requests_per_minute'] ?? 10);
        $dailyLimit = (int)($limits['requests_per_user_day'] ?? 100);
        $monthlyBudget = (float)(($feature === 'tts' ? ($limits['monthly_tts_budget'] ?? 50.0) : ($limits['monthly_stt_budget'] ?? 100.0)));

        if ((int)($usage['minute_requests'] ?? 0) >= $minuteLimit) {
            return ['allowed' => false, 'message' => "Per-minute voice {$feature} limit reached ({$minuteLimit}). Try again shortly or type your request instead."];
        }
        if ((int)($usage['today_requests'] ?? 0) >= $dailyLimit) {
            return ['allowed' => false, 'message' => "Daily voice {$feature} limit reached ({$dailyLimit}). You can type your request instead."];
        }
        if ((float)($usage['month_cost'] ?? 0) >= $monthlyBudget) {
            return ['allowed' => false, 'message' => "Monthly voice {$feature} budget reached. You can continue with typed AI requests."];
        }

        return ['allowed' => true];
    }
    
    /**
     * Track voice usage
     */
    private function trackUsage($feature, $provider, $durationMs, $estimatedCost = 0) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_usage (company_id, user_id, feature, provider, duration_ms, estimated_cost)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$this->companyId, $this->userId, $feature, $provider, $durationMs, $estimatedCost]);
    }
    
    // === AUDIT ===
    
    /**
     * Create audit log entry
     */
    private function auditLog($eventType, $transcriptionId = null, $details = null) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_audit (user_id, company_id, event_type, transcription_id, details)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $this->userId, $this->companyId, $eventType,
            $transcriptionId, $details ? json_encode($details) : null
        ]);
    }
    
    // === RETENTION ===
    
    /**
     * Apply voice retention policies
     */
    public function applyRetentionPolicy() {
        $policy = $this->getRetentionPolicy();
        
        $cleaned = 0;
        
        if (!empty($policy['auto_delete_audio'])) {
            $cleaned += $this->purgeVoiceAudioFiles((int)($policy['audio_retention_days'] ?? 7));
        }

        if (!empty($policy['auto_delete_transcripts'])) {
            $stmt = $this->db->prepare(
                "DELETE FROM ai_voice_transcriptions
                 WHERE company_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
            );
            $stmt->execute([$this->companyId, (int)($policy['transcript_retention_days'] ?? 90)]);
            $cleaned += $stmt->rowCount();
        }

        $stmt = $this->db->prepare(
            "DELETE FROM ai_voice_transcriptions
             WHERE company_id = ? AND status IN ('completed','failed','cancelled')
             AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$this->companyId, (int)($policy['transcript_retention_days'] ?? 90)]);
        $cleaned += $stmt->rowCount();
        
        // Clean TTS cache
        $cleaned += $this->ttsService->cleanExpiredCache();
        
        // Clean old audit logs
        $stmt = $this->db->prepare(
            "DELETE FROM ai_voice_audit 
             WHERE company_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL ? DAY)"
        );
        $stmt->execute([$this->companyId, (int)($policy['metadata_retention_days'] ?? 365)]);
        $cleaned += $stmt->rowCount();
        
        return ['cleaned' => $cleaned];
    }
    
    // === AUDIO CLEANUP ===
    
    /**
     * Schedule temporary audio file cleanup
     */
    private function scheduleAudioCleanup($audioPath) {
        // Mark for cleanup (actual cleanup happens via retention policy)
        // For browser-based STT, audio is processed client-side and not stored server-side
    }
    
    // === STATS ===
    
    /**
     * Get voice stats for management dashboard
     */
    public function getStats() {
        $stats = [];
        
        // Total transcriptions
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as total, 
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    SUM(duration_ms) as total_duration_ms
             FROM ai_voice_transcriptions WHERE company_id = ?"
        );
        $stmt->execute([$this->companyId]);
        $stats['transcriptions'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // STT usage
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as requests, SUM(estimated_cost) as cost
             FROM ai_voice_usage WHERE company_id = ? AND feature = 'stt' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$this->companyId]);
        $stats['stt_30d'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // TTS usage
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) as requests, SUM(estimated_cost) as cost
             FROM ai_voice_usage WHERE company_id = ? AND feature = 'tts' AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $stmt->execute([$this->companyId]);
        $stats['tts_30d'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Failure rate
        $total = $stats['transcriptions']['total'] ?? 0;
        $failed = $stats['transcriptions']['failed'] ?? 0;
        $stats['failure_rate'] = $total > 0 ? round($failed / $total * 100, 2) : 0;
        
        return $stats;
    }

    /**
     * Get configured voice providers and settings.
     */
    public function getProviderConfigs() {
        $stmt = $this->db->prepare("SELECT * FROM ai_voice_config WHERE company_id = ? ORDER BY provider_type, priority DESC, provider ASC");
        $stmt->execute([$this->companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Upsert a voice provider configuration.
     */
    public function saveProviderConfig($providerType, $provider, array $configData = [], $priority = 0, $isActive = true) {
        if (!in_array($providerType, ['stt', 'tts'], true)) {
            return ['success' => false, 'error' => 'Invalid provider type.'];
        }

        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_config (company_id, provider, provider_type, config_data, is_active, priority)
             VALUES (?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE config_data = VALUES(config_data), is_active = VALUES(is_active), priority = VALUES(priority)"
        );
        $stmt->execute([
            $this->companyId,
            trim($provider),
            $providerType,
            json_encode($configData),
            $isActive ? 1 : 0,
            (int)$priority,
        ]);

        $this->auditLog('settings_changed', null, [
            'provider_type' => $providerType,
            'provider' => $provider,
        ]);

        return ['success' => true];
    }

    /**
     * Get retention policy for the company.
     */
    public function getRetentionPolicy() {
        $stmt = $this->db->prepare("SELECT * FROM ai_voice_retention_policies WHERE company_id = ?");
        $stmt->execute([$this->companyId]);
        $policy = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($policy) {
            return $policy;
        }

        return [
            'audio_retention_days' => 7,
            'transcript_retention_days' => 90,
            'tts_cache_retention_days' => 1,
            'metadata_retention_days' => 365,
            'auto_delete_audio' => 1,
            'auto_delete_transcripts' => 0,
        ];
    }

    /**
     * Upsert retention policy.
     */
    public function saveRetentionPolicy(array $policy) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_retention_policies
                (company_id, audio_retention_days, transcript_retention_days, tts_cache_retention_days, metadata_retention_days, auto_delete_audio, auto_delete_transcripts)
             VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                audio_retention_days = VALUES(audio_retention_days),
                transcript_retention_days = VALUES(transcript_retention_days),
                tts_cache_retention_days = VALUES(tts_cache_retention_days),
                metadata_retention_days = VALUES(metadata_retention_days),
                auto_delete_audio = VALUES(auto_delete_audio),
                auto_delete_transcripts = VALUES(auto_delete_transcripts)"
        );
        $stmt->execute([
            $this->companyId,
            (int)($policy['audio_retention_days'] ?? 7),
            (int)($policy['transcript_retention_days'] ?? 90),
            (int)($policy['tts_cache_retention_days'] ?? 1),
            (int)($policy['metadata_retention_days'] ?? 365),
            !empty($policy['auto_delete_audio']) ? 1 : 0,
            !empty($policy['auto_delete_transcripts']) ? 1 : 0,
        ]);

        $this->auditLog('settings_changed', null, ['scope' => 'retention']);
        return ['success' => true];
    }

    private function estimateCost($feature, $provider, $durationMs = 0, $text = '') {
        if ($provider === 'browser') {
            return 0;
        }

        $minutes = max(0, (float)$durationMs) / 60000.0;
        if ($feature === 'stt') {
            return round($minutes * 0.006, 6);
        }

        $chars = mb_strlen((string)$text);
        return round(($chars / 1000.0) * 0.01, 6);
    }

    private function purgeVoiceAudioFiles($retentionDays) {
        $dir = __DIR__ . '/../../uploads/voice_audio/';
        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = time() - max(1, (int)$retentionDays) * 86400;
        $removed = 0;
        foreach (glob($dir . '*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < $cutoff) {
                @unlink($file);
                $removed++;
            }
        }

        return $removed;
    }
    
    // === MANAGEMENT CENTER ===
    
    /**
     * Get voice management dashboard data
     */
    public function getManagementDashboard() {
        return [
            'stats' => $this->getStats(),
            'providers' => [
                'stt' => $this->sttService->getActiveProviders(),
                'tts' => $this->ttsService->getActiveProviders()
            ],
            'provider_configs' => $this->getProviderConfigs(),
            'retention_policy' => $this->getRetentionPolicy(),
            'kill_switches' => [
                'voice_input' => $this->isFeatureEnabled('voice_input'),
                'stt' => $this->isFeatureEnabled('stt'),
                'tts' => $this->isFeatureEnabled('tts'),
                'dictation' => $this->isFeatureEnabled('dictation'),
                'voice_commands' => $this->isFeatureEnabled('voice_commands')
            ],
            'limits' => $this->getCurrentLimits()
        ];
    }
    
    /**
     * Get current usage limits
     */
    private function getCurrentLimits() {
        $configPath = __DIR__ . '/../config/ai_central_config.php';
        $config = file_exists($configPath) ? require $configPath : [];
        return $config['voice']['limits'] ?? [
            'max_audio_duration_ms' => 120000,
            'max_file_size_bytes' => 26214400,
            'requests_per_minute' => 10,
            'requests_per_user_day' => 100,
            'monthly_stt_budget' => 100.00,
            'monthly_tts_budget' => 50.00
        ];
    }
}
