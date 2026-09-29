<?php
/**
 * AI Research Session Service
 * 
 * F19: Shared Research Sessions — create, add findings, notes, summaries.
 * All methods are static and take $db as first parameter.
 */

class AIResearchService {

    /**
     * Create a research session.
     */
    public static function create($db, $data) {
        $workspaceId = (int)($data['workspace_id'] ?? 0);
        $title       = trim($data['title'] ?? '');
        $objective   = trim($data['objective'] ?? '');
        $createdBy   = (int)($data['created_by'] ?? 0);

        if ($workspaceId <= 0 || $title === '') {
            return ['success' => false, 'error' => 'Missing required fields'];
        }

        $stmt = $db->prepare("INSERT INTO ai_research_sessions (workspace_id, title, objective, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$workspaceId, $title, $objective, $createdBy]);
        $sessionId = (int)$db->lastInsertId();

        AIWorkspaceService::logActivity($db, $workspaceId, $createdBy, 'research_started', 'research_session', $sessionId, ['title' => $title]);

        return ['success' => true, 'session_id' => $sessionId];
    }

    /**
     * Get a research session by ID.
     */
    public static function getById($db, $sessionId) {
        $stmt = $db->prepare("SELECT r.*, u.name as creator_name
            FROM ai_research_sessions r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.session_id = ?");
        $stmt->execute([(int)$sessionId]);
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$session) return null;

        // Get notes
        $noteStmt = $db->prepare("SELECT n.*, u.name as author_name, f.title as finding_title
            FROM ai_research_notes n
            LEFT JOIN users u ON u.id = n.author_user_id
            LEFT JOIN ai_findings f ON f.finding_id = n.finding_id
            WHERE n.session_id = ?
            ORDER BY n.created_at");
        $noteStmt->execute([(int)$sessionId]);
        $session['notes'] = $noteStmt->fetchAll(PDO::FETCH_ASSOC);

        return $session;
    }

    /**
     * List research sessions for a workspace.
     */
    public static function listByWorkspace($db, $workspaceId) {
        $stmt = $db->prepare("SELECT r.*, u.name as creator_name,
            (SELECT COUNT(*) FROM ai_research_notes WHERE session_id = r.session_id) as note_count
            FROM ai_research_sessions r
            LEFT JOIN users u ON u.id = r.created_by
            WHERE r.workspace_id = ?
            ORDER BY r.created_at DESC");
        $stmt->execute([(int)$workspaceId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Add a note to a research session.
     */
    public static function addNote($db, $sessionId, $userId, $content, $type = 'note', $findingId = null) {
        $validTypes = ['question','finding','note','summary'];
        if (!in_array($type, $validTypes)) $type = 'note';

        $stmt = $db->prepare("INSERT INTO ai_research_notes (session_id, author_user_id, note_type, content, finding_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([(int)$sessionId, (int)$userId, $type, $content, $findingId ? (int)$findingId : null]);
        $noteId = (int)$db->lastInsertId();

        // Update session timestamp
        $db->prepare("UPDATE ai_research_sessions SET updated_at = NOW() WHERE session_id = ?")->execute([(int)$sessionId]);

        return ['success' => true, 'note_id' => $noteId];
    }

    /**
     * Get notes for a research session.
     */
    public static function getNotes($db, $sessionId) {
        $stmt = $db->prepare("SELECT n.*, u.name as author_name
            FROM ai_research_notes n
            LEFT JOIN users u ON u.id = n.author_user_id
            WHERE n.session_id = ?
            ORDER BY n.created_at");
        $stmt->execute([(int)$sessionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate a summary for a research session.
     */
    public static function generateSummary($db, $sessionId) {
        $session = self::getById($db, $sessionId);
        if (!$session) return ['success' => false, 'error' => 'Session not found'];

        $notes = $session['notes'] ?? [];
        $questions = array_filter($notes, fn($n) => $n['note_type'] === 'question');
        $findings = array_filter($notes, fn($n) => $n['note_type'] === 'finding');
        $summaries = array_filter($notes, fn($n) => $n['note_type'] === 'summary');

        $summary = [
            'session' => $session['title'],
            'objective' => $session['objective'],
            'total_notes' => count($notes),
            'questions_asked' => count($questions),
            'findings_recorded' => count($findings),
            'summaries_made' => count($summaries),
            'created_by' => $session['creator_name'],
            'status' => $session['status'],
        ];

        return ['success' => true, 'summary' => $summary];
    }

    /**
     * Complete a research session.
     */
    public static function complete($db, $sessionId) {
        $db->prepare("UPDATE ai_research_sessions SET status = 'completed' WHERE session_id = ?")->execute([(int)$sessionId]);
        return ['success' => true];
    }

    /**
     * Archive a research session.
     */
    public static function archive($db, $sessionId) {
        $db->prepare("UPDATE ai_research_sessions SET status = 'archived' WHERE session_id = ?")->execute([(int)$sessionId]);
        return ['success' => true];
    }

    /**
     * Get research stats for a workspace.
     */
    public static function getStats($db, $workspaceId) {
        $stmt = $db->prepare("SELECT 
            COUNT(*) as total_sessions,
            SUM(status = 'active') as active,
            SUM(status = 'completed') as completed,
            SUM(status = 'archived') as archived
            FROM ai_research_sessions WHERE workspace_id = ?");
        $stmt->execute([(int)$workspaceId]);
        $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $noteStmt = $db->prepare("SELECT COUNT(*) as total_notes FROM ai_research_notes n
            JOIN ai_research_sessions r ON r.session_id = n.session_id
            WHERE r.workspace_id = ?");
        $noteStmt->execute([(int)$workspaceId]);
        $stats['total_notes'] = (int)$noteStmt->fetchColumn();

        return $stats;
    }
}
