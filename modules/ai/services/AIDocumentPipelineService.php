<?php
/**
 * AI Document Pipeline Service
 * 
 * F20: Processing Pipeline — queue management, async processing, status lifecycle.
 * All methods are static and take $db as first parameter.
 */

class AIDocumentPipelineService {

    /**
     * Test 21: Acquire a processing lock for a document to prevent concurrent processing.
     */
    private static function acquireProcessingLock(PDO $db, int $documentId): bool
    {
        try {
            // Try to acquire lock using a dedicated lock table or GET_LOCK
            $lockKey = 'doc_proc_' . $documentId;
            
            // Clean up stale locks older than 30 minutes
            $db->prepare("DELETE FROM ai_document_processing_locks WHERE document_id = ? AND locked_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)")
               ->execute([$documentId]);
            
            // Try to acquire the lock
            $stmt = $db->prepare("INSERT INTO ai_document_processing_locks (document_id, locked_by, locked_at) VALUES (?, ?, NOW())");
            $result = $stmt->execute([$documentId, $_SESSION['user_id'] ?? 0]);
            
            return $result;
        } catch (\Throwable $e) {
            // If lock table doesn't exist, allow execution
            return true;
        }
    }

    /**
     * Test 21: Release the processing lock.
     */
    private static function releaseProcessingLock(PDO $db, int $documentId): void
    {
        try {
            $db->prepare("DELETE FROM ai_document_processing_locks WHERE document_id = ?")
               ->execute([$documentId]);
        } catch (\Throwable $e) {
            // Non-critical
        }
    }

    /**
     * Enqueue a document for processing.
     */
    public static function enqueue($db, $companyId, $documentId, $taskType = 'extract_text', $priority = 5) {
        $validTasks = ['extract_text','ocr','classify','structured_extract','validate','index','summarize','compare'];
        if (!in_array($taskType, $validTasks)) {
            return ['success' => false, 'error' => 'Invalid task type'];
        }

        if (!self::isProcessingEnabled($db)) {
            return ['success' => false, 'error' => 'Document processing is currently disabled.'];
        }

        // Check for existing pending task
        $existing = $db->prepare("SELECT queue_id FROM ai_document_queue 
            WHERE document_id = ? AND task_type = ? AND status IN ('pending','processing')");
        $existing->execute([(int)$documentId, $taskType]);
        if ($existing->fetch()) {
            return ['success' => false, 'error' => 'Task already queued'];
        }

        $stmt = $db->prepare("INSERT INTO ai_document_queue 
            (document_id, company_id, priority, task_type, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([(int)$documentId, $companyId, (int)$priority, $taskType]);

        // Update document processing status
        AIDocumentService::update($db, $documentId, ['processing_status' => 'queued']);

        return ['success' => true, 'queue_id' => (int)$db->lastInsertId()];
    }

    /**
     * Enqueue a full processing pipeline for a document.
     */
    public static function enqueueFullPipeline($db, $companyId, $documentId) {
        $tasks = ['extract_text', 'classify', 'structured_extract', 'validate', 'index'];
        $enqueued = 0;

        foreach ($tasks as $i => $task) {
            $r = self::enqueue($db, $companyId, $documentId, $task, 10 - $i);
            if ($r['success']) $enqueued++;
        }

        AIDocumentService::update($db, $documentId, ['processing_status' => 'queued']);

        return ['success' => true, 'tasks_enqueued' => $enqueued];
    }

    /**
     * Get next pending task from the queue.
     */
    public static function getNextTask($db, $companyId = null) {
        $sql = "SELECT q.*, d.file_name, d.mime_type, d.file_reference, d.file_hash, d.document_type, d.extracted_text
            FROM ai_document_queue q
            JOIN ai_documents d ON d.document_id = q.document_id
            WHERE q.status = 'pending' AND q.attempts < q.max_attempts";
        $params = [];
        if ($companyId) {
            $sql .= " AND q.company_id = ?";
            $params[] = (int)$companyId;
        }
        $sql .= " ORDER BY q.priority DESC, q.created_at ASC LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Test 21: Start processing a queued task with concurrency lock.
     */
    public static function startProcessing($db, $queueId) {
        $task = $db->prepare("SELECT * FROM ai_document_queue WHERE queue_id = ?");
        $task->execute([(int)$queueId]);
        $taskData = $task->fetch(PDO::FETCH_ASSOC);
        
        if (!$taskData) {
            return ['success' => false, 'task' => null];
        }

        // Test 21: Acquire processing lock to prevent concurrent processing of same document
        $lockAcquired = self::acquireProcessingLock($db, $taskData['document_id']);
        if (!$lockAcquired) {
            return ['success' => false, 'task' => $taskData, 'error' => 'Document is currently being processed by another request'];
        }

        $db->prepare("UPDATE ai_document_queue SET status = 'processing', started_at = NOW(), attempts = attempts + 1 WHERE queue_id = ? AND status = 'pending'")
            ->execute([(int)$queueId]);

        AIDocumentService::update($db, $taskData['document_id'], ['processing_status' => 'processing']);

        return ['success' => true, 'task' => $taskData];
    }

    /**
     * Complete a queued task.
     */
    public static function completeTask($db, $queueId, $resultData = null) {
        $db->prepare("UPDATE ai_document_queue SET status = 'completed', completed_at = NOW() WHERE queue_id = ?")
            ->execute([(int)$queueId]);

        $task = $db->prepare("SELECT * FROM ai_document_queue WHERE queue_id = ?");
        $task->execute([(int)$queueId]);
        $taskData = $task->fetch(PDO::FETCH_ASSOC);

        if ($taskData) {
            // Update document status based on task type
            switch ($taskData['task_type']) {
                case 'extract_text':
                    AIDocumentService::update($db, $taskData['document_id'], [
                        'processing_status' => 'processing',
                        'extracted_text' => $resultData['text'] ?? null,
                        'ocr_confidence' => $resultData['confidence'] ?? null,
                    ]);
                    break;
                case 'classify':
                    AIDocumentService::update($db, $taskData['document_id'], [
                        'document_type' => $resultData['document_type'] ?? 'unknown',
                        'classification_confidence' => $resultData['confidence'] ?? null,
                        'classification_reason' => $resultData['reason'] ?? null,
                    ]);
                    break;
                case 'validate':
                    AIDocumentService::update($db, $taskData['document_id'], [
                        'processing_status' => 'ready',
                        'extraction_status' => 'validated',
                    ]);
                    break;
            }

            // Log audit
            $doc = AIDocumentService::getById($db, $taskData['document_id']);
            if ($doc) {
                $actionMap = [
                    'extract_text' => 'processing_started',
                    'ocr' => 'ocr_completed',
                    'classify' => 'classification_suggested',
                    'structured_extract' => 'extraction_completed',
                    'validate' => 'extraction_reviewed',
                    'index' => 'knowledge_indexed',
                ];
                $action = $actionMap[$taskData['task_type']] ?? 'processing_started';
                AIDocumentService::logAudit($db, $doc['company_id'], $taskData['document_id'], $action, $doc['created_by']);
            }
        }

        return ['success' => true];
    }

    /**
     * Mark a task as failed.
     */
    public static function failTask($db, $queueId, $errorMessage) {
        $db->prepare("UPDATE ai_document_queue SET status = 'failed', error_message = ?, completed_at = NOW() WHERE queue_id = ?")
            ->execute([$errorMessage, (int)$queueId]);

        $task = $db->prepare("SELECT * FROM ai_document_queue WHERE queue_id = ?");
        $task->execute([(int)$queueId]);
        $taskData = $task->fetch(PDO::FETCH_ASSOC);

        if ($taskData && $taskData['attempts'] >= $taskData['max_attempts']) {
            AIDocumentService::update($db, $taskData['document_id'], ['processing_status' => 'failed']);
        }

        return ['success' => true];
    }

    /**
     * Cancel a queued task.
     */
    public static function cancelTask($db, $queueId) {
        $db->prepare("UPDATE ai_document_queue SET status = 'cancelled', completed_at = NOW() WHERE queue_id = ? AND status = 'pending'")
            ->execute([(int)$queueId]);
        return ['success' => true];
    }

    /**
     * Retry a failed task.
     */
    public static function retryTask($db, $queueId) {
        $db->prepare("UPDATE ai_document_queue SET status = 'pending', error_message = NULL, attempts = 0 WHERE queue_id = ? AND status = 'failed'")
            ->execute([(int)$queueId]);
        return ['success' => true];
    }

    /**
     * Process next batch of tasks.
     */
    public static function processBatch($db, $companyId, $maxConcurrent = 3) {
        // Check concurrent limit
        $running = $db->prepare("SELECT COUNT(*) FROM ai_document_queue WHERE company_id = ? AND status = 'processing'");
        $running->execute([$companyId]);
        $runningCount = (int)$running->fetchColumn();

        $available = $maxConcurrent - $runningCount;
        if ($available <= 0) {
            return ['success' => true, 'processed' => 0, 'reason' => 'Concurrency limit reached'];
        }

        $processed = 0;
        for ($i = 0; $i < $available; $i++) {
            $task = self::getNextTask($db, $companyId);
            if (!$task) break;

            $startResult = self::startProcessing($db, $task['queue_id']);
            if ($startResult['success']) {
                $processed++;
            }
        }

        return ['success' => true, 'processed' => $processed];
    }

    /**
     * Get queue status for a company.
     */
    public static function getQueueStats($db, $companyId) {
        $stmt = $db->prepare("SELECT 
            status, task_type, COUNT(*) as count,
            MIN(created_at) as oldest,
            MAX(completed_at) as newest
            FROM ai_document_queue
            WHERE company_id = ?
            GROUP BY status, task_type
            ORDER BY FIELD(status, 'pending','processing','completed','failed','cancelled'), task_type");
        $stmt->execute([$companyId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private static function isProcessingEnabled($db) {
        if (!class_exists('AIKillSwitchService')) {
            return true;
        }

        return AIKillSwitchService::isEnabled($db, 'document_processing');
    }
}
