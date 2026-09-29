<?php
/**
 * AI F24 — Loop Detection Service.
 *
 * Detects and prevents infinite AI tool-calling loops.
 * Tracks conversation patterns and identifies repetitive cycles.
 */
class AILoopDetector
{
    const MAX_CONSECUTIVE_IDENTICAL = 3;
    const MAX_TOTAL_ITERATIONS = 10;
    const TIME_WINDOW_SECONDS = 60;

    private static array $loopCache = [];

    /**
     * Record a tool call iteration for loop detection.
     */
    public static function recordIteration(PDO $db, int $conversationId, string $iterationKey, array $context = []): array
    {
        $now = time();
        $cacheKey = "conv:{$conversationId}";

        // Initialize or refresh session window
        if (!isset(self::$loopCache[$cacheKey]) || ($now - self::$loopCache[$cacheKey]['last_activity']) > self::TIME_WINDOW_SECONDS) {
            self::$loopCache[$cacheKey] = [
                'conversation_id' => $conversationId,
                'iterations' => [],
                'first_activity' => $now,
                'last_activity' => $now,
                'total_iterations' => 0,
            ];
        }

        $session = &self::$loopCache[$cacheKey];
        $session['last_activity'] = $now;
        $session['total_iterations']++;

        // Record this iteration
        $iterationRecord = [
            'key' => $iterationKey,
            'time' => $now,
            'context_hash' => self::hashContext($context),
        ];
        $session['iterations'][] = $iterationRecord;

        // Keep only recent iterations within the time window
        $cutoff = $now - self::TIME_WINDOW_SECONDS;
        $session['iterations'] = array_filter($session['iterations'], fn($i) => $i['time'] >= $cutoff);
        $session['iterations'] = array_values($session['iterations']);

        // Persist to database for durability
        self::persistIteration($db, $conversationId, $iterationKey, $context);

        // Analyze for loops
        $analysis = self::analyzeLoop($db, $session, $conversationId);

        return $analysis;
    }

    /**
     * Persist iteration to database.
     */
    private static function persistIteration(PDO $db, int $conversationId, string $iterationKey, array $context): void
    {
        try {
            // Clean up old iterations (older than 24 hours)
            $stmt = $db->prepare("DELETE FROM ai_loop_detection WHERE conversation_id = ? AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
            $stmt->execute([$conversationId]);

            // Insert this iteration
            $stmt = $db->prepare("
                INSERT INTO ai_loop_detection
                    (conversation_id, iteration_key, context_hash, context_json, detected_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $conversationId,
                $iterationKey,
                self::hashContext($context),
                json_encode($context),
            ]);
        } catch (\Throwable $e) {
            // Non-critical - continue loop detection
        }
    }

    /**
     * Analyze for loop patterns.
     */
    private static function analyzeLoop(PDO $db, array $session, int $conversationId): array
    {
        $iterations = $session['iterations'];
        $analysis = [
            'loop_detected' => false,
            'loop_type' => null,
            'loop_count' => 0,
            'consecutive_identical' => 0,
            'total_iterations' => count($iterations),
            'suggestion' => null,
            'block' => false,
        ];

        if (count($iterations) < 2) {
            return $analysis;
        }

        // Check for consecutive identical iterations
        $lastKey = end($iterations)['key'];
        $consecutiveCount = 0;
        foreach (array_reverse($iterations) as $iter) {
            if ($iter['key'] === $lastKey || self::hashesMatch($iter['context_hash'], $lastKey)) {
                $consecutiveCount++;
            } else {
                break;
            }
        }
        $analysis['consecutive_identical'] = $consecutiveCount;

        if ($consecutiveCount >= self::MAX_CONSECUTIVE_IDENTICAL) {
            $analysis['loop_detected'] = true;
            $analysis['loop_type'] = 'consecutive_identical';
            $analysis['loop_count'] = $consecutiveCount;
            $analysis['suggestion'] = 'Consecutive identical iterations detected. Breaking the loop.';
            $analysis['block'] = true;
        }

        // Check for pattern-based loops (alternating between 2+ keys)
        if (!$analysis['loop_detected'] && count($iterations) >= 4) {
            $recentKeys = array_map(fn($i) => $i['key'], array_slice($iterations, -4));
            if (count(array_unique($recentKeys)) <= 2) {
                $analysis['loop_detected'] = true;
                $analysis['loop_type'] = 'alternating_pattern';
                $analysis['loop_count'] = 4;
                $analysis['suggestion'] = 'Alternating pattern detected between ' . implode(' and ', array_unique($recentKeys)) . '. Breaking the loop.';
                $analysis['block'] = true;
            }
        }

        // Check total iterations
        if ($session['total_iterations'] >= self::MAX_TOTAL_ITERATIONS) {
            $analysis['loop_detected'] = true;
            $analysis['loop_type'] = 'max_iterations_exceeded';
            $analysis['loop_count'] = $session['total_iterations'];
            $analysis['suggestion'] = 'Maximum total iterations exceeded (' . self::MAX_TOTAL_ITERATIONS . '). Stopping.';
            $analysis['block'] = true;
        }

        // Check for time-based loops (too many iterations in short time)
        if (count($iterations) >= 5 && ($session['last_activity'] - $session['first_activity']) < 10) {
            $analysis['loop_detected'] = true;
            $analysis['loop_type'] = 'burst_pattern';
            $analysis['loop_count'] = count($iterations);
            $analysis['suggestion'] = 'Burst pattern detected - too many iterations in short time.';
            $analysis['block'] = true;
        }

        // Log critical loop detection
        if ($analysis['loop_detected'] && $analysis['block']) {
            self::logLoopDetection($db, $conversationId, $analysis);
        }

        return $analysis;
    }

    /**
     * Check if two context hashes match (simplified comparison).
     */
    private static function hashesMatch(string $hash1, string $hash2): bool
    {
        if (empty($hash1) || empty($hash2)) return false;
        // Compare first 8 chars as a quick check
        return substr($hash1, 0, 8) === substr($hash2, 0, 8);
    }

    /**
     * Generate a hash of the context for comparison.
     */
    private static function hashContext(array $context): string
    {
        $relevantKeys = ['action', 'tool_name', 'intent', 'target', 'data_classification'];
        $filtered = [];
        foreach ($relevantKeys as $key) {
            if (isset($context[$key])) {
                $filtered[$key] = $context[$key];
            }
        }
        return md5(json_encode($filtered));
    }

    /**
     * Log loop detection event.
     */
    private static function logLoopDetection(PDO $db, int $conversationId, array $analysis): void
    {
        try {
            $stmt = $db->prepare("
                INSERT INTO ai_loop_detection_events
                    (conversation_id, loop_type, loop_count, detected_at, context_json)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $stmt->execute([
                $conversationId,
                $analysis['loop_type'],
                $analysis['loop_count'],
                json_encode($analysis),
            ]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Get loop detection statistics for a conversation.
     */
    public static function getStats(PDO $db, int $conversationId): array
    {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) as total_iterations,
                       COUNT(DISTINCT iteration_key) as unique_keys
                FROM ai_loop_detection
                WHERE conversation_id = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ");
            $stmt->execute([$conversationId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $db->prepare("
                SELECT loop_type, COUNT(*) as count
                FROM ai_loop_detection_events
                WHERE conversation_id = ?
                AND detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY loop_type
            ");
            $stmt->execute([$conversationId]);
            $loopTypes = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            return [
                'total_iterations' => (int) ($stats['total_iterations'] ?? 0),
                'unique_keys' => (int) ($stats['unique_keys'] ?? 0),
                'loop_types' => $loopTypes,
                'loop_detected' => !empty($loopTypes),
            ];
        } catch (\Throwable $e) {
            return ['total_iterations' => 0, 'unique_keys' => 0, 'loop_types' => [], 'loop_detected' => false];
        }
    }

    /**
     * Clear loop detection cache for a conversation.
     */
    public static function clearCache(?int $conversationId = null): void
    {
        if ($conversationId !== null) {
            $cacheKey = "conv:{$conversationId}";
            unset(self::$loopCache[$cacheKey]);
        } else {
            self::$loopCache = [];
        }
    }

    /**
     * Get the current iteration count for a conversation from cache.
     */
    public static function getCurrentIterationCount(int $conversationId): int
    {
        $cacheKey = "conv:{$conversationId}";
        if (isset(self::$loopCache[$cacheKey])) {
            return self::$loopCache[$cacheKey]['total_iterations'];
        }
        return 0;
    }
}
