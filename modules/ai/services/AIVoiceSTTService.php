<?php
/**
 * AI F21: Voice Interface — STT Provider Abstraction
 * Speech-to-Text provider interface and browser/web-speech fallback
 */

class AIVoiceSTTProvider {
    
    private $db;
    private $companyId;
    
    // Supported audio MIME types
    private $allowedMimeTypes = [
        'audio/webm', 'audio/wav', 'audio/mpeg', 'audio/mp3',
        'audio/ogg', 'audio/flac', 'audio/m4a', 'audio/mp4',
        'audio/webm;codecs=opus', 'audio/webm;codecs=pcm',
        'audio/wav;codecs=pcm', 'audio/x-wav'
    ];
    
    private $maxFileSizeBytes = 25 * 1024 * 1024; // 25 MB
    private $maxDurationMs = 120000; // 2 minutes
    private $minDurationMs = 500; // 0.5 seconds
    
    public function __construct($db, $companyId) {
        $this->db = $db;
        $this->companyId = $companyId;
    }
    
    /**
     * Get active STT providers for company
     */
    public function getActiveProviders() {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_config 
             WHERE company_id = ? AND provider_type = 'stt' AND is_active = 1 
             ORDER BY priority DESC"
        );
        $stmt->execute([$this->companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get the best available provider
     */
    public function resolveProvider() {
        $providers = $this->getActiveProviders();
        if (empty($providers)) {
            return [
                'provider' => 'browser',
                'model' => 'web-speech-api',
                'capabilities' => ['transcribe', 'detectLanguage', 'realTime']
            ];
        }
        return [
            'provider' => $providers[0]['provider'],
            'model' => $providers[0]['config_data'] ? json_decode($providers[0]['config_data'], true)['model'] ?? null : null,
            'capabilities' => ['transcribe', 'detectLanguage']
        ];
    }
    
    /**
     * Validate audio before processing
     */
    public function validateAudio($mimeType, $fileSizeBytes, $durationMs = null) {
        $errors = [];
        
        if ($fileSizeBytes > $this->maxFileSizeBytes) {
            $errors[] = 'Audio file too large. Maximum size is ' . ($this->maxFileSizeBytes / 1024 / 1024) . ' MB.';
        }
        
        if ($fileSizeBytes < 100) {
            $errors[] = 'Audio file appears to be empty.';
        }
        
        if ($durationMs !== null) {
            if ($durationMs > $this->maxDurationMs) {
                $errors[] = 'Audio too long. Maximum duration is ' . ($this->maxDurationMs / 1000) . ' seconds.';
            }
            if ($durationMs < $this->minDurationMs) {
                $errors[] = 'Audio too short. Minimum duration is ' . ($this->minDurationMs / 1000) . ' seconds.';
            }
        }
        
        // Check MIME type (normalize codec variants)
        $baseMime = explode(';', $mimeType)[0];
        if (!in_array($baseMime, array_map(function($m) { return explode(';', $m)[0]; }, $this->allowedMimeTypes))) {
            $errors[] = 'Unsupported audio format. Allowed: WebM, WAV, MP3, OGG, FLAC, M4A.';
        }
        
        // Check company voice settings
        $config = $this->getCompanyConfig();
        if (!empty($config['external_blocked']) && $this->providerNeedsExternalAPI($config['provider'] ?? 'browser')) {
            $errors[] = 'External speech provider is blocked by company policy.';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
    
    /**
     * Create a transcription record
     */
    public function createTranscription($userId, $mimeType, $fileSizeBytes, $durationMs = null, $language = null, $audioPath = null) {
        $provider = $this->resolveProvider();
        
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_transcriptions 
             (user_id, company_id, provider, model, language, duration_ms, file_size_bytes, mime_type, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([
            $userId, $this->companyId,
            $provider['provider'], $provider['model'] ?? null,
            $language, $durationMs ?? 0, $fileSizeBytes, $mimeType
        ]);
        
        return [
            'id' => (int)$this->db->lastInsertId(),
            'provider' => $provider['provider'],
            'model' => $provider['model'] ?? null
        ];
    }
    
    /**
     * Complete transcription with result
     */
    public function completeTranscription($transcriptionId, $text, $confidence = null, $detectedLanguage = null, $metadata = null) {
        $stmt = $this->db->prepare(
            "UPDATE ai_voice_transcriptions 
             SET status = 'completed', transcript_text = ?, confidence = ?, 
                 detected_language = ?, raw_metadata = ?, completed_at = NOW()
             WHERE id = ? AND company_id = ?"
        );
        $stmt->execute([$text, $confidence, $detectedLanguage, $metadata ? json_encode($metadata) : null, $transcriptionId, $this->companyId]);
        
        return ['success' => $stmt->rowCount() > 0];
    }
    
    /**
     * Fail transcription
     */
    public function failTranscription($transcriptionId, $errorMessage) {
        $stmt = $this->db->prepare(
            "UPDATE ai_voice_transcriptions 
             SET status = 'failed', error_message = ?, completed_at = NOW()
             WHERE id = ? AND company_id = ?"
        );
        $stmt->execute([$errorMessage, $transcriptionId, $this->companyId]);
        
        return ['success' => $stmt->rowCount() > 0];
    }
    
    /**
     * Get transcription by ID
     */
    public function getTranscription($transcriptionId) {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_transcriptions WHERE id = ? AND company_id = ?"
        );
        $stmt->execute([$transcriptionId, $this->companyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * List transcriptions for user
     */
    public function listTranscriptions($userId, $limit = 20, $offset = 0) {
        $stmt = $this->db->prepare(
            "SELECT id, provider, language, detected_language, duration_ms, status, 
                    LEFT(transcript_text, 200) as transcript_preview, confidence, created_at, completed_at
             FROM ai_voice_transcriptions 
             WHERE user_id = ? AND company_id = ?
             ORDER BY created_at DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $this->companyId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check company config
     */
    private function getCompanyConfig() {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_config WHERE company_id = ? AND provider_type = 'stt' ORDER BY priority DESC"
        );
        $stmt->execute([$this->companyId]);
        $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return !empty($configs) ? $configs[0] : [];
    }
    
    /**
     * Check if provider needs external API
     */
    private function providerNeedsExternalAPI($provider) {
        return in_array($provider, ['openai', 'nvidia', 'google', 'azure', 'deepgram']);
    }
    
    /**
     * Get audio format support info
     */
    public function getSupportedFormats() {
        return [
            'mime_types' => $this->allowedMimeTypes,
            'max_file_size_bytes' => $this->maxFileSizeBytes,
            'max_duration_ms' => $this->maxDurationMs,
            'min_duration_ms' => $this->minDurationMs
        ];
    }
    
    /**
     * Get provider capabilities
     */
    public function getProviderCapabilities($provider) {
        $capabilities = [
            'browser' => ['transcribe', 'detectLanguage', 'realTime', 'free'],
            'openai' => ['transcribe', 'detectLanguage', 'highAccuracy', 'multiLanguage'],
            'nvidia' => ['transcribe', 'detectLanguage', 'highAccuracy', 'realTime'],
            'google' => ['transcribe', 'detectLanguage', 'highAccuracy', 'multiLanguage'],
            'azure' => ['transcribe', 'detectLanguage', 'highAccuracy', 'realTime'],
            'deepgram' => ['transcribe', 'detectLanguage', 'realTime', 'streaming']
        ];
        return $capabilities[$provider] ?? ['transcribe'];
    }
}
