<?php
/**
 * AI F32 — Communication Translation Service
 * 
 * Safe translation with business value preservation.
 * Sections 14-15 of F32 specification.
 */

class AICommunicationTranslationService {

    private $companyId;
    private $userId;

    public function __construct($companyId, $userId) {
        $this->companyId = $companyId;
        $this->userId = $userId;
    }

    /**
     * Translate a business communication while preserving critical values.
     *
     * @param string $text Text to translate
     * @param string $targetLanguage Target language code (ar, fr, de, es, etc.)
     * @param array  $options sourceLanguage, preserveNumbers, preserveDates, etc.
     * @return array Translation with preserved values and side-by-side view
     */
    public function translate($text, $targetLanguage, $options = []) {
        $sourceLanguage = $options['source_language'] ?? 'en';
        $preserveNumbers = $options['preserve_numbers'] ?? true;
        $preserveDates = $options['preserve_dates'] ?? true;
        $preserveCurrency = $options['preserve_currency'] ?? true;
        $preserveIdentifiers = $options['preserve_identifiers'] ?? true;

        if ($sourceLanguage === $targetLanguage) {
            return ['original' => $text, 'translated' => $text, 'language' => $targetLanguage, 'unchanged' => true];
        }

        // 1. Extract protected values before translation
        $protected = $this->extractProtectedValues($text, [
            'numbers'      => $preserveNumbers,
            'dates'        => $preserveDates,
            'currency'     => $preserveCurrency,
            'identifiers'  => $preserveIdentifiers,
        ]);

        // 2. Replace protected values with placeholders
        $safeText = $this->replaceWithPlaceholders($text, $protected);

        // 3. Build translation prompt
        $prompt = $this->buildTranslationPrompt($safeText, $sourceLanguage, $targetLanguage);

        // 4. Call AI provider
        $aiResult = $this->callAIProvider($prompt);

        // 5. Restore protected values
        $translated = $this->restoreProtectedValues($aiResult['text'] ?? '', $protected);

        // 6. Verify critical values are preserved
        $verification = $this->verifyPreservation($text, $translated, $protected);

        // 7. Record F23 event
        $this->recordEvent('translate', 0, [
            'source' => $sourceLanguage,
            'target' => $targetLanguage,
            'preserved_count' => count($protected),
            'verification' => $verification,
        ]);

        return [
            'original'    => $text,
            'translated'  => $translated,
            'source_lang' => $sourceLanguage,
            'target_lang' => $targetLanguage,
            'protected'   => $protected,
            'verification'=> $verification,
            'provider'    => $aiResult['provider'] ?? null,
            'model'       => $aiResult['model'] ?? null,
        ];
    }

    /**
     * Get supported languages from the AI provider.
     */
    public function getSupportedLanguages() {
        return [
            ['code' => 'en', 'name' => 'English', 'native' => 'English'],
            ['code' => 'ar', 'name' => 'Arabic', 'native' => 'العربية'],
            ['code' => 'fr', 'name' => 'French', 'native' => 'Français'],
            ['code' => 'de', 'name' => 'German', 'native' => 'Deutsch'],
            ['code' => 'es', 'name' => 'Spanish', 'native' => 'Español'],
            ['code' => 'it', 'name' => 'Italian', 'native' => 'Italiano'],
            ['code' => 'pt', 'name' => 'Portuguese', 'native' => 'Português'],
            ['code' => 'zh', 'name' => 'Chinese', 'native' => '中文'],
            ['code' => 'ja', 'name' => 'Japanese', 'native' => '日本語'],
            ['code' => 'ko', 'name' => 'Korean', 'native' => '한국어'],
            ['code' => 'tr', 'name' => 'Turkish', 'native' => 'Türkçe'],
            ['code' => 'hi', 'name' => 'Hindi', 'native' => 'हिन्दी'],
            ['code' => 'nl', 'name' => 'Dutch', 'native' => 'Nederlands'],
            ['code' => 'ru', 'name' => 'Russian', 'native' => 'Русский'],
        ];
    }

    // ============================================================
    // PRIVATE METHODS
    // ============================================================

    private function extractProtectedValues($text, $options) {
        $protected = [];
        $counter = 0;

        // Numbers (including amounts)
        if ($options['numbers']) {
            if (preg_match_all('/\b\d{1,3}(?:,\d{3})*(?:\.\d{1,4})?\b/', $text, $m)) {
                foreach ($m[0] as $val) {
                    $protected[] = ['type' => 'number', 'value' => $val, 'placeholder' => "§NUM_{$counter}§"];
                    $counter++;
                }
            }
        }

        // Dates
        if ($options['dates']) {
            $datePatterns = [
                '/\b\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}\b/',
                '/\b\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}\b/',
                '/\b(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{1,2},?\s+\d{4}\b/i',
                '/\b\d{1,2}\s+(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-z]*\s+\d{4}\b/i',
            ];
            foreach ($datePatterns as $pattern) {
                if (preg_match_all($pattern, $text, $m)) {
                    foreach ($m[0] as $val) {
                        $protected[] = ['type' => 'date', 'value' => $val, 'placeholder' => "§DATE_{$counter}§"];
                        $counter++;
                    }
                }
            }
        }

        // Currency
        if ($options['currency']) {
            if (preg_match_all('/(?:USD|EUR|GBP|SAR|AED|QAR|KWD|BHD|OMR|¥|£|€|\$)\s*\d[\d,]*(?:\.\d{1,2})?|\d[\d,]*(?:\.\d{1,2})?\s*(?:USD|EUR|GBP|SAR|AED|QAR|KWD|BHD|OMR|USD|EUR|GBP)/i', $text, $m)) {
                foreach ($m[0] as $val) {
                    $protected[] = ['type' => 'currency', 'value' => $val, 'placeholder' => "§CUR_{$counter}§"];
                    $counter++;
                }
            }
        }

        // Identifiers (invoice numbers, PO numbers, etc.)
        if ($options['identifiers']) {
            if (preg_match_all('/\b(?:INV|PO|SO|QUO|TKT|REF|ORD)-?\d[\w\-]*\b/i', $text, $m)) {
                foreach ($m[0] as $val) {
                    $protected[] = ['type' => 'identifier', 'value' => $val, 'placeholder' => "§ID_{$counter}§"];
                    $counter++;
                }
            }
        }

        return $protected;
    }

    private function replaceWithPlaceholders($text, $protected) {
        foreach ($protected as $p) {
            $text = str_replace($p['value'], $p['placeholder'], $text);
        }
        return $text;
    }

    private function restoreProtectedValues($text, $protected) {
        foreach ($protected as $p) {
            $text = str_replace($p['placeholder'], $p['value'], $text);
        }
        return $text;
    }

    private function buildTranslationPrompt($text, $sourceLang, $targetLang) {
        $langNames = [
            'en' => 'English', 'ar' => 'Arabic', 'fr' => 'French', 'de' => 'German',
            'es' => 'Spanish', 'it' => 'Italian', 'pt' => 'Portuguese', 'zh' => 'Chinese',
            'ja' => 'Japanese', 'ko' => 'Korean', 'tr' => 'Turkish', 'hi' => 'Hindi',
            'nl' => 'Dutch', 'ru' => 'Russian',
        ];

        $source = $langNames[$sourceLang] ?? $sourceLang;
        $target = $langNames[$targetLang] ?? $targetLang;

        $prompt = "Translate this business communication from {$source} to {$target}.\n\n";
        $prompt .= "RULES:\n";
        $prompt .= "- Preserve the exact business meaning.\n";
        $prompt .= "- Do NOT translate numbers, dates, currency amounts, or identifiers (they use placeholder tokens).\n";
        $prompt .= "- Keep placeholder tokens (§...§) exactly as they appear.\n";
        $prompt .= "- Maintain professional business tone.\n";
        $prompt .= "- Preserve line breaks and formatting.\n";
        $prompt .= "- If a word has no direct translation, use the closest business equivalent.\n\n";
        $prompt .= "Text to translate:\n{$text}\n";

        return $prompt;
    }

    private function verifyPreservation($original, $translated, $protected) {
        $issues = [];

        foreach ($protected as $p) {
            if (stripos($translated, $p['value']) === false) {
                $issues[] = "Missing {$p['type']}: {$p['value']}";
            }
        }

        return [
            'passed' => empty($issues),
            'issues' => $issues,
            'protected_count' => count($protected),
        ];
    }

    private function callAIProvider($prompt) {
        if (class_exists('AIProviderService')) {
            $providerService = new AIProviderService();
            if (method_exists($providerService, 'complete')) {
                $result = $providerService->complete($prompt);
                if ($result) return $result;
            }
        }
        return ['text' => '', 'provider' => 'none', 'model' => 'none', 'tokens_used' => 0, 'cost_usd' => 0];
    }

    private function recordEvent($eventType, $entityId, $metadata = []) {
        $db = $this->getDb();
        if (!$db) return;
        $sql = "INSERT INTO ai_communication_events (company_id, event_type, entity_type, entity_id, user_id, metadata)
                VALUES (?, ?, 'translation', ?, ?, ?)";
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
