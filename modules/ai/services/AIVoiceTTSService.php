<?php
/**
 * AI F21: Voice Interface — TTS Provider Abstraction
 * Text-to-Speech provider interface with browser fallback
 */

class AIVoiceTTSProvider {
    
    private $db;
    private $companyId;
    private $userId;
    private $cacheDir;
    
    public function __construct($db, $companyId, $userId = null) {
        $this->db = $db;
        $this->companyId = $companyId;
        $this->userId = $userId;
        $this->cacheDir = __DIR__ . '/../../uploads/voice_tts/';
    }
    
    /**
     * Get active TTS providers for company
     */
    public function getActiveProviders() {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_config 
             WHERE company_id = ? AND provider_type = 'tts' AND is_active = 1 
             ORDER BY priority DESC"
        );
        $stmt->execute([$this->companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Resolve best TTS provider
     */
    public function resolveProvider() {
        $providers = $this->getActiveProviders();
        if (empty($providers)) {
            return [
                'provider' => 'browser',
                'model' => 'speechSynthesis',
                'capabilities' => ['play', 'pause', 'stop', 'voice', 'speed', 'language']
            ];
        }
        return [
            'provider' => $providers[0]['provider'],
            'model' => json_decode($providers[0]['config_data'], true)['model'] ?? null,
            'capabilities' => ['play', 'pause', 'stop', 'voice', 'speed', 'language']
        ];
    }
    
    /**
     * Generate TTS audio (or use browser fallback)
     */
    public function generate($text, $userId, $language = null, $voice = null, $speed = 1.0) {
        if (strlen($text) > 5000) {
            return ['success' => false, 'error' => 'Text too long for TTS. Maximum 5000 characters.'];
        }
        
        $textHash = hash('sha256', $text . '|' . ($language ?? '') . '|' . ($voice ?? '') . '|' . $speed);
        
        // Check cache first
        $cached = $this->getCached($textHash);
        if ($cached) {
            return ['success' => true, 'cached' => true, 'text_hash' => $textHash];
        }
        
        $provider = $this->resolveProvider();
        
        // Browser TTS doesn't generate a file — handled client-side
        if ($provider['provider'] === 'browser') {
            $this->createCacheRecord($textHash, $text, $language, $voice, $speed, null, null, 0, 'completed');
            return [
                'success' => true,
                'provider' => 'browser',
                'use_browser_tts' => true,
                'text' => $text,
                'language' => $language,
                'voice' => $voice,
                'speed' => $speed,
                'text_hash' => $textHash
            ];
        }
        
        // Server-side TTS provider (placeholder for future OpenAI/Azure/etc.)
        $cachePath = $this->cacheDir . $textHash . '.mp3';
        
        // Create cache record
        $this->createCacheRecord($textHash, $text, $language, $voice, $speed, $cachePath, null, null, 'generating');
        
        // For now, return browser fallback since server TTS is not yet implemented
        $this->updateCacheStatus($textHash, 'completed', null, null, 0);
        return [
            'success' => true,
            'provider' => 'browser',
            'use_browser_tts' => true,
            'text' => $text,
            'language' => $language,
            'voice' => $voice,
            'speed' => $speed,
            'text_hash' => $textHash
        ];
    }
    
    /**
     * Get cached TTS
     */
    private function getCached($textHash) {
        $stmt = $this->db->prepare(
            "SELECT * FROM ai_voice_tts_cache 
             WHERE text_hash = ? AND company_id = ? AND status = 'completed'
             AND (expires_at IS NULL OR expires_at > NOW())"
        );
        $stmt->execute([$textHash, $this->companyId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Create cache record
     */
    private function createCacheRecord($textHash, $text, $language, $voice, $speed, $filePath, $fileSize, $duration, $status) {
        $stmt = $this->db->prepare(
            "INSERT INTO ai_voice_tts_cache 
             (user_id, company_id, provider, text_hash, text_preview, language, voice, speed, 
              file_path, file_size_bytes, duration_ms, status, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 24 HOUR))
             ON DUPLICATE KEY UPDATE status = VALUES(status)"
        );
        $stmt->execute([
            $this->userId ?? 1, $this->companyId, 'browser', $textHash,
            substr($text, 0, 200), $language, $voice, $speed,
            $filePath, $fileSize ?? 0, $duration ?? 0, $status
        ]);
    }
    
    /**
     * Update cache status
     */
    private function updateCacheStatus($textHash, $status, $fileSize, $duration) {
        $stmt = $this->db->prepare(
            "UPDATE ai_voice_tts_cache SET status = ?, file_size_bytes = ?, duration_ms = ? 
             WHERE text_hash = ? AND company_id = ?"
        );
        $stmt->execute([$status, $fileSize, $duration, $textHash, $this->companyId]);
    }
    
    /**
     * Clean expired cache
     */
    public function cleanExpiredCache() {
        $stmt = $this->db->prepare(
            "DELETE FROM ai_voice_tts_cache 
             WHERE company_id = ? AND expires_at IS NOT NULL AND expires_at < NOW()"
        );
        $stmt->execute([$this->companyId]);
        return $stmt->rowCount();
    }
    
    /**
     * Get TTS capabilities
     */
    public function getCapabilities($provider) {
        $capabilities = [
            'browser' => ['play', 'pause', 'stop', 'replay', 'voice', 'speed', 'language', 'free'],
            'openai' => ['play', 'pause', 'stop', 'replay', 'voice', 'speed', 'language', 'highQuality'],
            'azure' => ['play', 'pause', 'stop', 'replay', 'voice', 'speed', 'language', 'highQuality', 'neural'],
            'google' => ['play', 'pause', 'stop', 'replay', 'voice', 'speed', 'language', 'highQuality']
        ];
        return $capabilities[$provider] ?? ['play', 'stop'];
    }
}
