<?php
/**
 * AI F21: Voice Interface — Main UI Page
 * Push-to-talk voice interface for ERP users
 */

require_once __DIR__ . '/../bootstrap.php';
$db = getDB();
$companyId = $_SESSION['company_id'] ?? 1;
$userId = $_SESSION['user_id'] ?? 1;
$currentTheme = $_SESSION['theme'] ?? 'ibm-light';

$voiceService = new AIVoiceService($db, $companyId, $userId);

// Handle API actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'upload_audio':
            $audioData = $_POST['audio_data'] ?? null;
            $mimeType = $_POST['mime_type'] ?? 'audio/webm';
            $durationMs = $_POST['duration_ms'] ? (int)$_POST['duration_ms'] : null;
            $language = $_POST['language'] ?? null;
            
            if (!$audioData) {
                echo json_encode(['success' => false, 'error' => 'No audio data provided.']);
                exit;
            }
            
            // Decode base64 audio
            $audioBin = base64_decode($audioData);
            $result = $voiceService->processAudio($audioBin, $mimeType, $durationMs, $language);
            echo json_encode($result);
            exit;
            
        case 'submit_transcription':
            $transcriptionId = (int)($_POST['transcription_id'] ?? 0);
            $text = $_POST['text'] ?? '';
            $confidence = $_POST['confidence'] ? (float)$_POST['confidence'] : null;
            $language = $_POST['detected_language'] ?? null;
            
            $result = $voiceService->submitTranscriptionResult($transcriptionId, $text, $confidence, $language);
            echo json_encode($result);
            exit;
            
        case 'send_voice_command':
            $transcriptionId = (int)($_POST['transcription_id'] ?? 0);
            $text = $_POST['text'] ?? '';
            $context = $_POST['context'] ? json_decode($_POST['context'], true) : null;
            
            $result = $voiceService->processVoiceCommand($transcriptionId, $text, $context);
            echo json_encode($result);
            exit;
            
        case 'generate_tts':
            $text = $_POST['text'] ?? '';
            $language = $_POST['language'] ?? null;
            $voice = $_POST['voice'] ?? null;
            $speed = $_POST['speed'] ? (float)$_POST['speed'] : 1.0;
            
            $result = $voiceService->generateTTS($text, $language, $voice, $speed);
            echo json_encode($result);
            exit;
            
        case 'dictate':
            $text = $_POST['text'] ?? '';
            $targetField = $_POST['target_field'] ?? null;
            
            $result = $voiceService->processDictation($text, $targetField);
            echo json_encode($result);
            exit;
            
        case 'list_shortcuts':
            $shortcuts = $voiceService->listShortcuts();
            echo json_encode(['success' => true, 'shortcuts' => $shortcuts]);
            exit;
            
        case 'set_shortcut':
            $command = $_POST['command'] ?? '';
            $action = $_POST['target_action'] ?? '';
            $url = $_POST['target_url'] ?? null;
            $desc = $_POST['description'] ?? null;
            
            $result = $voiceService->setShortcut($command, $action, $url, $desc);
            echo json_encode($result);
            exit;
            
        case 'delete_shortcut':
            $shortcutId = (int)($_POST['shortcut_id'] ?? 0);
            $result = $voiceService->deleteShortcut($shortcutId);
            echo json_encode($result);
            exit;
            
        case 'get_transcriptions':
            $limit = (int)($_POST['limit'] ?? 20);
            $offset = (int)($_POST['offset'] ?? 0);
            $list = $voiceService->sttService->listTranscriptions($userId, $limit, $offset);
            echo json_encode(['success' => true, 'transcriptions' => $list]);
            exit;
            
        case 'get_stats':
            $stats = $voiceService->getStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
            
        case 'get_dashboard':
            $dashboard = $voiceService->getManagementDashboard();
            echo json_encode(['success' => true, 'dashboard' => $dashboard]);
            exit;

        case 'get_providers':
            echo json_encode(['success' => true, 'providers' => $voiceService->getProviderConfigs()]);
            exit;

        case 'save_provider':
            $result = $voiceService->saveProviderConfig(
                $_POST['provider_type'] ?? 'stt',
                $_POST['provider'] ?? 'browser',
                json_decode($_POST['config_data'] ?? '{}', true) ?: [],
                (int)($_POST['priority'] ?? 0),
                !empty($_POST['is_active'])
            );
            echo json_encode($result);
            exit;

        case 'get_retention':
            echo json_encode(['success' => true, 'retention' => $voiceService->getRetentionPolicy()]);
            exit;

        case 'save_retention':
            $result = $voiceService->saveRetentionPolicy([
                'audio_retention_days' => $_POST['audio_retention_days'] ?? 7,
                'transcript_retention_days' => $_POST['transcript_retention_days'] ?? 90,
                'tts_cache_retention_days' => $_POST['tts_cache_retention_days'] ?? 1,
                'metadata_retention_days' => $_POST['metadata_retention_days'] ?? 365,
                'auto_delete_audio' => !empty($_POST['auto_delete_audio']),
                'auto_delete_transcripts' => !empty($_POST['auto_delete_transcripts']),
            ]);
            echo json_encode($result);
            exit;

        case 'toggle_kill_switch':
            $feature = $_POST['feature'] ?? '';
            $enabled = !empty($_POST['enabled']);
            $map = [
                'voice_input' => 'voice_input',
                'stt' => 'speech_to_text',
                'tts' => 'text_to_speech',
                'dictation' => 'voice_dictation',
                'voice_commands' => 'voice_commands',
            ];
            if (!isset($map[$feature])) {
                echo json_encode(['success' => false, 'error' => 'Unknown feature.']);
                exit;
            }
            $result = AIKillSwitchService::toggle($db, $map[$feature], $enabled, $userId, 'Updated from Voice Interface');
            echo json_encode($result);
            exit;
            
        case 'check_limits':
            $feature = $_POST['feature'] ?? 'stt';
            $result = $voiceService->checkUsageLimits($feature);
            echo json_encode(['success' => true, 'limit_check' => $result]);
            exit;
            
        case 'get_supported_formats':
            $formats = $voiceService->sttService->getSupportedFormats();
            echo json_encode(['success' => true, 'formats' => $formats]);
            exit;
            
        case 'apply_retention':
            $result = $voiceService->applyRetentionPolicy();
            echo json_encode(['success' => true, 'retention' => $result]);
            exit;
            
        // ================================================================
        // F28: Governance & Confirmation (static methods)
        // ================================================================
        case 'check_voice_governance':
            $command = $_POST['command'] ?? '';
            $result = AIVoiceGovernanceService::enforce($db, $userId, $companyId, $command);
            echo json_encode($result);
            exit;
            
        case 'request_voice_confirmation':
            $actionType = $_POST['action_type'] ?? 'other';
            $actionId = $_POST['action_id'] ?? null;
            $parameters = json_decode($_POST['parameters'] ?? '{}', true) ?: [];
            $riskLevel = $_POST['risk_level'] ?? 'medium';
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $messageId = (int)($_POST['message_id'] ?? 0);
            $result = AIVoiceConfirmationService::createConfirmation(
                $db, $userId, $companyId, $actionType, $actionId,
                $parameters, $riskLevel, $sessionId ?: null, $messageId ?: null
            );
            echo json_encode($result);
            exit;
            
        case 'confirm_voice_action':
            $confirmId = (int)($_POST['confirmation_id'] ?? 0);
            $confirmKey = $_POST['confirmation_key'] ?? '';
            $currentParams = !empty($_POST['current_parameters']) ? json_decode($_POST['current_parameters'], true) : null;

            if ($confirmKey === '' && $confirmId > 0 && class_exists('AIVoiceConfirmationService')) {
                $confirmation = AIVoiceConfirmationService::getById($db, $confirmId, $companyId, $userId);
                $confirmKey = $confirmation['confirmation_key'] ?? '';
            }

            if ($confirmKey === '') {
                echo json_encode(['success' => false, 'error' => 'Confirmation key required.']);
                exit;
            }

            $result = AIVoiceConfirmationService::confirm($db, $confirmKey, $userId, $companyId, $currentParams);
            if (!empty($result['success']) && ($result['action_type'] ?? '') === 'voice_command') {
                $parameters = is_array($result['parameters'] ?? null) ? $result['parameters'] : [];
                $confirmedText = trim((string) ($parameters['text'] ?? ''));
                $confirmedContext = is_array($parameters['context'] ?? null) ? $parameters['context'] : [];
                $confirmedTranscriptionId = (int) ($parameters['transcription_id'] ?? 0);

                if ($confirmedText !== '') {
                    $executionResult = $voiceService->processVoiceCommand($confirmedTranscriptionId, $confirmedText, $confirmedContext);
                    $result['execution_result'] = $executionResult;
                }
            }

            echo json_encode($result);
            exit;
            
        case 'reject_voice_action':
            $confirmId = (int)($_POST['confirmation_id'] ?? 0);
            $confirmKey = $_POST['confirmation_key'] ?? '';
            if ($confirmKey === '' && $confirmId > 0 && class_exists('AIVoiceConfirmationService')) {
                $confirmation = AIVoiceConfirmationService::getById($db, $confirmId, $companyId, $userId);
                $confirmKey = $confirmation['confirmation_key'] ?? '';
            }
            $result = AIVoiceConfirmationService::deny($db, $confirmKey, $userId, $companyId);
            echo json_encode($result);
            exit;
            
        case 'get_voice_confirmations':
            $result = AIVoiceConfirmationService::getPending($db, $userId, $companyId);
            echo json_encode(['success' => true, 'confirmations' => $result]);
            exit;
            
        // ================================================================
        // F28: Conversational Context (static methods)
        // ================================================================
        case 'start_voice_session':
            $language = $_POST['language'] ?? 'en';
            $result = AIVoiceContextService::createSession($db, $userId, $companyId, $language);
            echo json_encode($result);
            exit;
            
        case 'add_voice_message':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $role = $_POST['role'] ?? 'user';
            $content = $_POST['content'] ?? '';
            $metadata = json_decode($_POST['metadata'] ?? '{}', true) ?: [];
            $result = AIVoiceContextService::addMessage($db, $sessionId, $userId, $companyId, $role, $content, $metadata);
            echo json_encode($result);
            exit;
            
        case 'get_voice_context':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            $history = AIVoiceContextService::getConversationHistory($db, $sessionId);
            echo json_encode(['success' => true, 'history' => $history]);
            exit;
            
        case 'end_voice_session':
            $sessionId = (int)($_POST['session_id'] ?? 0);
            AIVoiceContextService::endSession($db, $sessionId);
            echo json_encode(['success' => true]);
            exit;
            
        // ================================================================
        // F28: F25 Agent Tasks from Voice (static methods)
        // ================================================================
        case 'create_agent_task_from_voice':
            $description = $_POST['description'] ?? '';
            $priority = $_POST['priority'] ?? 'medium';
            $result = AIVoiceF25Integration::createTaskFromVoice(
                $db, $userId, $companyId, $description, ['priority' => $priority]
            );
            echo json_encode($result);
            exit;
            
        case 'voice_task_status':
            $taskId = (int)($_POST['task_id'] ?? 0);
            $result = AIVoiceF25Integration::getTaskStatus($db, $taskId, $companyId);
            echo json_encode($result);
            exit;
            
        // ================================================================
        // F28: F26 Intelligence from Voice (static methods)
        // ================================================================
        case 'create_intelligence_from_voice':
            $command = $_POST['command'] ?? '';
            $insight = $_POST['insight'] ?? '';
            // Create intelligence event via AIVoiceF26Integration
            $result = AIVoiceF26Integration::queryIntelligence($db, $userId, $companyId, $insight);
            echo json_encode($result);
            exit;
            
        case 'voice_watchlist_add':
            $entityType = $_POST['entity_type'] ?? '';
            $entityId = (int)($_POST['entity_id'] ?? 0);
            $condition = $_POST['condition'] ?? '';
            // Add to watchlist via AIWatchlistService
            try {
                $ws = new AIWatchlistService($db, $companyId);
                $result = $ws->addWatchlist($userId, $entityType, $entityId, $condition);
                echo json_encode($result);
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        // ================================================================
        // F28: F27 Document Questions from Voice (static methods)
        // ================================================================
        case 'ask_document_by_voice':
            $documentId = (int)($_POST['document_id'] ?? 0);
            $question = $_POST['question'] ?? '';
            $result = AIVoiceF27Integration::queryDocument(
                $db, $userId, $companyId, $question, $documentId ?: null
            );
            echo json_encode($result);
            exit;
            
        case 'voice_document_search':
            $query = $_POST['query'] ?? '';
            // Use AIDocumentSearchService for voice search
            try {
                $docSearch = new AIDocumentSearchService($db, $companyId, $userId);
                $results = $docSearch->search($query, 10);
                echo json_encode(['success' => true, 'documents' => $results]);
            } catch (\Throwable $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
            
        // ================================================================
        // F28: Enhanced send_voice_command with F24 governance + AI chat
        // ================================================================
        case 'send_voice_command':
            $transcriptionId = (int)($_POST['transcription_id'] ?? 0);
            $text = $_POST['text'] ?? '';
            $context = $_POST['context'] ? json_decode($_POST['context'], true) : null;
            
            if (empty($text)) {
                echo json_encode(['success' => false, 'error' => 'No text provided.']);
                exit;
            }
            
            // F28: Run F24 governance check first
            $govCheck = AIVoiceGovernanceService::enforce($db, $userId, $companyId, $text, $context ?? []);
            
            if (!$govCheck['allowed']) {
                // F23: Log governance denial
                try {
                    $db->prepare("INSERT INTO ai_voice_audit (user_id, company_id, event_type, details) VALUES (?, ?, 'voice_governance_denied', ?)")
                        ->execute([$userId, $companyId, json_encode(['text' => substr($text, 0, 500), 'reason' => $govCheck['reason']])]);
                } catch (\Throwable $e) { /* non-critical */ }
                
                echo json_encode([
                    'success' => false,
                    'type' => 'governance_denied',
                    'error' => $govCheck['reason'] ?? 'This action is not allowed.',
                    'risk_level' => $govCheck['risk_level'] ?? 'low',
                ]);
                exit;
            }
            
            // F28: If high risk, create confirmation instead of executing
            if ($govCheck['requires_confirmation'] || $govCheck['requires_review']) {
                $confirmResult = AIVoiceConfirmationService::createConfirmation(
                    $db, $userId, $companyId,
                    'voice_command', null,
                    ['text' => $text, 'context' => $context, 'transcription_id' => $transcriptionId],
                    $govCheck['risk_level']
                );
                
                // F23: Log confirmation request
                try {
                    $db->prepare("INSERT INTO ai_voice_audit (user_id, company_id, event_type, transcription_id, details) VALUES (?, ?, 'voice_confirmation_required', ?, ?)")
                        ->execute([$userId, $companyId, $transcriptionId ?: null, json_encode(['text' => substr($text, 0, 500), 'risk_level' => $govCheck['risk_level']])]);
                } catch (\Throwable $e) { /* non-critical */ }
                
                echo json_encode([
                    'success' => false,
                    'type' => 'confirmation_required',
                    'message' => 'This action requires confirmation. Please confirm on screen.',
                    'confirmation' => $confirmResult,
                    'risk_level' => $govCheck['risk_level'],
                ]);
                exit;
            }
            
            // F28: Check for F26 intelligence queries
            $f26Result = AIVoiceF26Integration::queryIntelligence($db, $userId, $companyId, $text);
            if ($f26Result['success'] && ($f26Result['is_intelligence_query'] ?? false)) {
                // Filter response for secrets
                $filteredResponse = AIVoiceGovernanceService::filterResponse($db, $userId, $companyId, $f26Result['response']);
                
                // F23: Log intelligence query
                try {
                    $db->prepare("INSERT INTO ai_voice_audit (user_id, company_id, event_type, transcription_id, details) VALUES (?, ?, 'voice_intelligence_query', ?, ?)")
                        ->execute([$userId, $companyId, $transcriptionId ?: null, json_encode(['text' => substr($text, 0, 500)])]);
                } catch (\Throwable $e) { /* non-critical */ }
                
                echo json_encode([
                    'success' => true,
                    'type' => 'intelligence_response',
                    'response' => $filteredResponse,
                    'can_create_task' => $f26Result['can_create_task'] ?? false,
                ]);
                exit;
            }
            
            // F28: Check for F27 document queries
            $f27Result = AIVoiceF27Integration::queryDocument($db, $userId, $companyId, $text);
            if ($f27Result['success'] && ($f27Result['is_document_query'] ?? false)) {
                $filteredResponse = AIVoiceGovernanceService::filterResponse($db, $userId, $companyId, $f27Result['response'] ?? '');
                
                try {
                    $db->prepare("INSERT INTO ai_voice_audit (user_id, company_id, event_type, transcription_id, details) VALUES (?, ?, 'voice_document_query', ?, ?)")
                        ->execute([$userId, $companyId, $transcriptionId ?: null, json_encode(['text' => substr($text, 0, 500)])]);
                } catch (\Throwable $e) { /* non-critical */ }
                
                echo json_encode([
                    'success' => true,
                    'type' => 'document_response',
                    'response' => $filteredResponse,
                    'needs_document' => $f27Result['needs_document'] ?? false,
                ]);
                exit;
            }
            
            // F28: Default — process through normal AI pipeline
            $result = $voiceService->processVoiceCommand($transcriptionId, $text, $context);
            
            // F23: Log voice command
            try {
                $db->prepare("INSERT INTO ai_voice_audit (user_id, company_id, event_type, transcription_id, details) VALUES (?, ?, 'voice_command_processed', ?, ?)")
                    ->execute([$userId, $companyId, $transcriptionId ?: null, json_encode(['text' => substr($text, 0, 500), 'type' => $result['type'] ?? 'unknown', 'risk_level' => $govCheck['risk_level']])]);
            } catch (\Throwable $e) { /* non-critical */ }
            
            echo json_encode($result);
            exit;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Unknown action']);
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $currentTheme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AI Voice Interface</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        .panel { display: none; }
        .panel.active { display: block; }

        /* Voice Recorder */
        .recorder-container {
            text-align: center; padding: 40px 20px;
        }
        .mic-button {
            width: 120px; height: 120px; border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none; color: white; font-size: 36px; cursor: pointer;
            transition: all 0.3s; box-shadow: 0 4px 20px rgba(102,126,234,0.4);
        }
        .mic-button:hover { transform: scale(1.05); box-shadow: 0 6px 30px rgba(102,126,234,0.6); }
        .mic-button.recording {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 4px 20px rgba(231,76,60,0.4);
            animation: pulse 1.5s infinite;
        }
        .mic-button:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        @keyframes pulse {
            0%, 100% { box-shadow: 0 4px 20px rgba(231,76,60,0.4); }
            50% { box-shadow: 0 4px 40px rgba(231,76,60,0.7); }
        }

        .recorder-status {
            margin-top: 16px; font-size: 14px; color: var(--ai-text-muted);
        }
        .recorder-timer {
            font-size: 28px; font-weight: 600; color: var(--ai-text-primary); margin: 12px 0;
            font-variant-numeric: tabular-nums;
        }
        .recorder-controls {
            margin-top: 16px; display: flex; gap: 12px; justify-content: center;
        }
        .btn {
            padding: 10px 20px; border-radius: 8px; border: none; cursor: pointer;
            font-size: 13px; font-weight: 500; transition: all 0.2s;
        }
        .btn-primary { background: var(--ai-accent); color: #fff; }
        .btn-primary:hover { background: var(--ai-accent-2); }
        .btn-danger { background: var(--ai-danger); color: #fff; }
        .btn-danger:hover { background: #c0392b; }
        .btn-secondary { background: rgba(148,163,184,0.18); color: var(--ai-text-secondary); }
        .btn-secondary:hover { background: rgba(148,163,184,0.28); }
        .btn-success { background: var(--ai-success); color: #fff; }
        .btn-success:hover { background: #219a52; }

        /* Transcription Preview */
        .transcript-preview {
            background: rgba(15, 23, 42, 0.55); border: 1px solid var(--ai-border); border-radius: 8px;
            padding: 16px; margin-top: 16px; min-height: 80px;
        }
        .transcript-preview textarea {
            width: 100%; border: none; background: transparent; resize: vertical;
            font-size: 15px; line-height: 1.6; outline: none; font-family: inherit;
            color: var(--ai-text-primary);
        }
        .transcript-actions {
            display: flex; gap: 8px; margin-top: 12px; justify-content: flex-end;
        }

        /* TTS Playback */
        .tts-player {
            display: flex; align-items: center; gap: 8px; padding: 12px;
            background: rgba(15, 23, 42, 0.55); border-radius: 8px; margin-top: 8px;
        }
        .tts-controls { display: flex; gap: 6px; }
        .tts-controls button {
            width: 32px; height: 32px; border-radius: 50%; border: none;
            background: var(--ai-accent); color: white; cursor: pointer; font-size: 12px;
        }

        /* History */
        .history-list { max-height: 400px; overflow-y: auto; }
        .history-item {
            padding: 12px; border-bottom: 1px solid var(--ai-border);
            display: flex; justify-content: space-between; align-items: center;
        }
        .history-item:last-child { border-bottom: none; }
        .history-text { font-size: 14px; color: var(--ai-text-primary); }
        .history-meta { font-size: 11px; color: var(--ai-text-muted); margin-top: 2px; }
        .status-badge {
            padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: 500;
        }
        .status-completed { background: rgba(52,211,153,0.15); color: var(--ai-success); }
        .status-failed { background: rgba(248,113,113,0.15); color: var(--ai-danger); }
        .status-pending { background: rgba(251,191,36,0.15); color: var(--ai-warning); }

        /* Shortcuts */
        .shortcut-list { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 12px; }
        .shortcut-card {
            padding: 14px; border: 1px solid var(--ai-border); border-radius: 8px;
            display: flex; justify-content: space-between; align-items: center;
        }
        .shortcut-card .command { font-weight: 600; font-size: 14px; color: var(--ai-text-primary); }
        .shortcut-card .action { font-size: 12px; color: var(--ai-text-muted); margin-top: 4px; }

        /* Dictation */
        .dictation-target {
            width: 100%; min-height: 100px; padding: 12px; border: 1px solid var(--ai-border);
            border-radius: 8px; font-size: 14px; resize: vertical; font-family: inherit;
            background: var(--ai-input-bg); color: var(--ai-text-primary);
        }

        /* Stats */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 16px; }
        .stat-card {
            background: rgba(15, 23, 42, 0.55); padding: 16px; border-radius: 8px; text-align: center;
        }
        .stat-card .value { font-size: 28px; font-weight: 700; color: var(--ai-accent); }
        .stat-card .label { font-size: 12px; color: var(--ai-text-muted); margin-top: 4px; }

        /* Kill Switches */
        .kill-switch-row {
            display: flex; justify-content: space-between; align-items: center;
            padding: 12px 0; border-bottom: 1px solid var(--ai-border);
        }
        .toggle {
            width: 44px; height: 24px; border-radius: 12px; border: none;
            background: #ccc; cursor: pointer; position: relative; transition: 0.3s;
        }
        .toggle.on { background: var(--ai-success); }
        .toggle::after {
            content: ''; position: absolute; top: 2px; left: 2px;
            width: 20px; height: 20px; border-radius: 50%;
            background: var(--ai-card-bg); transition: 0.3s;
        }
        .toggle.on::after { left: 22px; }

        /* Hidden */
        .hidden { display: none !important; }
    </style>
    <?php ai_render_shell_styles(); ?>
</head>
<body class="ai-module-page" data-theme="<?= $currentTheme ?>">
    <?php ai_render_module_nav('ai.voice'); ?>
<?php ai_render_page_styles(); ?>
<?php ai_render_page_header('AI Voice Interface', 'Speak naturally to interact with your ERP system', 'fas fa-microphone'); ?>

    <div class="ai-tabs">
        <div class="ai-tab active" onclick="showPanel('recorder')"><i class="fas fa-microphone"></i> Voice</div>
        <div class="ai-tab" onclick="showPanel('conversations')"><i class="fas fa-comments"></i> Conversations</div>
        <div class="ai-tab" onclick="showPanel('confirmations')"><i class="fas fa-shield-alt"></i> Confirmations</div>
        <div class="ai-tab" onclick="showPanel('history')"><i class="fas fa-history"></i> History</div>
        <div class="ai-tab" onclick="showPanel('shortcuts')"><i class="fas fa-bolt"></i> Shortcuts</div>
        <div class="ai-tab" onclick="showPanel('dictation')"><i class="fas fa-keyboard"></i> Dictation</div>
        <div class="ai-tab" onclick="showPanel('f25tasks')"><i class="fas fa-robot"></i> F25 Tasks</div>
        <div class="ai-tab" onclick="showPanel('f26intel')"><i class="fas fa-lightbulb"></i> F26 Intel</div>
        <div class="ai-tab" onclick="showPanel('f27docs')"><i class="fas fa-file-alt"></i> F27 Docs</div>
        <div class="ai-tab" onclick="showPanel('settings')"><i class="fas fa-cog"></i> Settings</div>
    </div>
    
    <div class="ai-page-container">
        <!-- Voice Recorder Panel -->
        <div class="panel active" id="panel-recorder">
            <div class="ai-content-card">
                <div class="recorder-container">
                    <button class="mic-button" id="micBtn" onclick="toggleRecording()">
                        <i class="fas fa-microphone"></i>
                    </button>
                    <div class="recorder-status" id="recorderStatus">Click to start recording</div>
                    <div class="recorder-timer hidden" id="recorderTimer">00:00</div>
                    
                    <div class="recorder-controls" id="recorderControls" style="display:none;">
                        <button class="btn btn-danger" onclick="cancelRecording()">
                            <i class="fas fa-times"></i> Cancel
                        </button>
                        <button class="btn btn-success" onclick="processRecording()" id="processBtn" disabled>
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
                
                <!-- Transcription Preview -->
                <div class="transcript-preview hidden" id="transcriptPreview">
                    <label style="font-size:12px;color: var(--ai-text-muted);margin-bottom:6px;display:block;">You said:</label>
                    <textarea id="transcriptText" rows="3" placeholder="Transcription will appear here..."></textarea>
                    <div class="transcript-actions">
                        <button class="btn btn-secondary" onclick="retryRecording()">
                            <i class="fas fa-redo"></i> Retry
                        </button>
                        <button class="btn btn-primary" onclick="sendVoiceCommand()">
                            <i class="fas fa-paper-plane"></i> Send
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- TTS Response Area -->
            <div class="ai-content-card hidden" id="ttsResponseCard">
                <h3><i class="fas fa-volume-up"></i> AI Response</h3>
                <div id="aiResponseText" style="line-height:1.6;"></div>
                <div class="tts-player" id="ttsPlayer" style="display:none;">
                    <div class="tts-controls">
                        <button onclick="ttsPlay()" title="Play"><i class="fas fa-play"></i></button>
                        <button onclick="ttsPause()" title="Pause"><i class="fas fa-pause"></i></button>
                        <button onclick="ttsStop()" title="Stop"><i class="fas fa-stop"></i></button>
                        <button onclick="ttsReplay()" title="Replay"><i class="fas fa-redo"></i></button>
                    </div>
                    <span style="font-size:12px;color: var(--ai-text-muted);">Listen to response</span>
                </div>
                <div style="margin-top:12px;">
                    <button class="btn btn-secondary" onclick="listenResponse()" id="listenBtn">
                        <i class="fas fa-headphones"></i> Listen
                    </button>
                </div>
            </div>
            
            <!-- Command Result -->
            <div class="ai-content-card hidden" id="commandResultCard">
                <h3><i class="fas fa-check-circle"></i> Command Result</h3>
                <div id="commandResult"></div>
            </div>
        </div>
        
        <!-- History Panel -->
        <div class="panel" id="panel-history">
            <div class="ai-content-card">
                <h3><i class="fas fa-history"></i> Voice History</h3>
                <div class="history-list" id="historyList">
                    <div style="text-align:center;padding:40px;color: var(--ai-text-muted);">
                        <i class="fas fa-microphone" style="font-size:48px;margin-bottom:12px;display:block;"></i>
                        No voice recordings yet
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Shortcuts Panel -->
        <div class="panel" id="panel-shortcuts">
            <div class="ai-content-card">
                <h3><i class="fas fa-bolt"></i> Voice Shortcuts</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">
                    Map voice commands to specific actions. Say the command to trigger it.
                </p>
                <div class="shortcut-list" id="shortcutList"></div>
                <div style="margin-top:16px;">
                    <button class="btn btn-primary" onclick="showAddShortcut()">
                        <i class="fas fa-plus"></i> Add Shortcut
                    </button>
                </div>
            </div>
            
            <!-- Add Shortcut Form -->
            <div class="ai-content-card hidden" id="addShortcutForm">
                <h3><i class="fas fa-plus"></i> New Shortcut</h3>
                <div style="display:grid;gap:12px;">
                    <input type="text" id="shortcutCommand" placeholder="Voice command (e.g. new task)" 
                           style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="text" id="shortcutAction" placeholder="Target action (e.g. open_tasks)" 
                           style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="text" id="shortcutUrl" placeholder="URL (optional)" 
                           style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="text" id="shortcutDesc" placeholder="Description" 
                           style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <div style="display:flex;gap:8px;">
                        <button class="btn btn-primary" onclick="saveShortcut()">Save</button>
                        <button class="btn btn-secondary" onclick="hideAddShortcut()">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Dictation Panel -->
        <div class="panel" id="panel-dictation">
            <div class="ai-content-card">
                <h3><i class="fas fa-keyboard"></i> Voice Dictation</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">
                    Dictate text into ERP fields. Click the microphone to start, then speak.
                </p>
                <div style="margin-bottom:12px;">
                    <select id="dictationField" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;width:100%;">
                        <option value="notes">Notes</option>
                        <option value="description">Description</option>
                        <option value="comments">Comments</option>
                        <option value="internal_notes">Internal Notes</option>
                    </select>
                </div>
                <textarea class="dictation-target" id="dictationTarget" placeholder="Dictated text will appear here..."></textarea>
                <div style="margin-top:12px;display:flex;gap:8px;">
                    <button class="btn btn-primary" onclick="startDictation()" id="dictateBtn">
                        <i class="fas fa-microphone"></i> Start Dictating
                    </button>
                    <button class="btn btn-secondary" onclick="clearDictation()">
                        <i class="fas fa-eraser"></i> Clear
                    </button>
                </div>
            </div>
        </div>
        
        <!-- F28: Conversations Panel -->
        <div class="panel" id="panel-conversations">
            <div class="ai-content-card">
                <h3><i class="fas fa-comments"></i> Voice Conversations</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">Multi-turn voice sessions with conversational context.</p>
                <div style="display:flex;gap:8px;margin-bottom:16px;">
                    <select id="convContextType" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;flex:1;">
                        <option value="general">General</option>
                        <option value="finance">Finance</option>
                        <option value="procurement">Procurement</option>
                        <option value="inventory">Inventory</option>
                        <option value="document">Document Q&A</option>
                    </select>
                    <button class="btn btn-primary" onclick="startVoiceSession()"><i class="fas fa-play"></i> Start Session</button>
                </div>
                <div id="activeSession" style="display:none;">
                    <div style="background: rgba(15, 23, 42, 0.55);border-radius:8px;padding:12px;margin-bottom:12px;max-height:400px;overflow-y:auto;" id="sessionMessages"></div>
                    <div style="display:flex;gap:8px;">
                        <input type="text" id="sessionInput" placeholder="Type or speak a message..." style="flex:1;padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                        <button class="btn btn-primary" onclick="sendSessionMessage()"><i class="fas fa-paper-plane"></i></button>
                        <button class="btn btn-secondary" onclick="endVoiceSession()"><i class="fas fa-stop"></i></button>
                    </div>
                </div>
                <div id="sessionHistory"></div>
            </div>
        </div>
        
        <!-- F28: Confirmations Panel -->
        <div class="panel" id="panel-confirmations">
            <div class="ai-content-card">
                <h3><i class="fas fa-shield-alt"></i> Voice Action Confirmations</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">High-risk voice actions requiring explicit confirmation before execution.</p>
                <div id="pendingConfirmations"></div>
            </div>
        </div>
        
        <!-- F28: F25 Tasks Panel -->
        <div class="panel" id="panel-f25tasks">
            <div class="ai-content-card">
                <h3><i class="fas fa-robot"></i> Voice → Agent Tasks</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">Create F25 agent tasks from voice commands.</p>
                <div style="display:grid;gap:12px;margin-bottom:16px;">
                    <textarea id="f25TaskDesc" rows="3" placeholder="Describe the agent task..." style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;resize:vertical;"></textarea>
                    <div style="display:flex;gap:8px;">
                        <select id="f25TaskPriority" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                            <option value="low">Low Priority</option>
                            <option value="medium" selected>Medium Priority</option>
                            <option value="high">High Priority</option>
                            <option value="urgent">Urgent</option>
                        </select>
                        <button class="btn btn-primary" onclick="createVoiceAgentTask()"><i class="fas fa-plus"></i> Create Task</button>
                    </div>
                </div>
                <div id="voiceTaskList"></div>
            </div>
        </div>
        
        <!-- F28: F26 Intelligence Panel -->
        <div class="panel" id="panel-f26intel">
            <div class="ai-content-card">
                <h3><i class="fas fa-lightbulb"></i> Voice → Intelligence</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">Create F26 intelligence insights and watchlists from voice.</p>
                <div style="display:grid;gap:12px;margin-bottom:16px;">
                    <textarea id="f26Insight" rows="2" placeholder="Describe the insight..." style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;resize:vertical;"></textarea>
                    <button class="btn btn-primary" onclick="createVoiceInsight()"><i class="fas fa-plus"></i> Create Insight</button>
                </div>
                <h3 style="margin-top:16px;"><i class="fas fa-eye"></i> Voice → Watchlist</h3>
                <div style="display:grid;gap:12px;margin-top:8px;">
                    <input type="text" id="watchEntity" placeholder="Entity type (e.g. invoice)" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="number" id="watchEntityId" placeholder="Entity ID" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="text" id="watchCondition" placeholder="Alert condition (e.g. amount > 10000)" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <button class="btn btn-primary" onclick="addVoiceWatchlist()"><i class="fas fa-plus"></i> Add to Watchlist</button>
                </div>
            </div>
        </div>
        
        <!-- F28: F27 Documents Panel -->
        <div class="panel" id="panel-f27docs">
            <div class="ai-content-card">
                <h3><i class="fas fa-file-alt"></i> Voice → Document Questions</h3>
                <p style="font-size:13px;color: var(--ai-text-muted);margin-bottom:16px;">Ask questions about documents by voice or search documents.</p>
                <div style="display:grid;gap:12px;">
                    <input type="number" id="f27DocId" placeholder="Document ID" style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <input type="text" id="f27Question" placeholder="Ask a question about the document..." style="padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <button class="btn btn-primary" onclick="askDocByVoice()"><i class="fas fa-search"></i> Ask</button>
                </div>
                <div id="docAnswer" style="margin-top:16px;display:none;"></div>
                <hr style="margin:20px 0;border:none;border-top:1px solid #eee;">
                <h3><i class="fas fa-search"></i> Voice Document Search</h3>
                <div style="display:flex;gap:8px;margin-top:8px;">
                    <input type="text" id="f27SearchQuery" placeholder="Search documents by voice..." style="flex:1;padding:10px;border: 1px solid var(--ai-border);border-radius:6px;">
                    <button class="btn btn-primary" onclick="voiceDocSearch()"><i class="fas fa-search"></i> Search</button>
                </div>
                <div id="docSearchResults" style="margin-top:16px;"></div>
            </div>
        </div>
        
        <!-- Settings Panel -->
        <div class="panel" id="panel-settings">
            <div class="ai-content-card">
                <h3><i class="fas fa-chart-bar"></i> Voice Usage Statistics</h3>
                <div class="stats-grid" id="statsGrid"></div>
            </div>

            <div class="ai-content-card">
                <h3><i class="fas fa-server"></i> Speech Providers</h3>
                <div id="providerConfigs" style="display:grid;gap:12px;margin-top:12px;"></div>
            </div>
            
            <div class="ai-content-card">
                <h3><i class="fas fa-database"></i> Voice Retention</h3>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Audio Days</label><input id="retAudio" type="number" min="1" class="w-full border rounded px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Transcript Days</label><input id="retTranscript" type="number" min="1" class="w-full border rounded px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">TTS Cache Days</label><input id="retTts" type="number" min="1" class="w-full border rounded px-3 py-2 text-sm"></div>
                    <div><label class="block text-xs font-semibold text-gray-600 mb-1">Metadata Days</label><input id="retMeta" type="number" min="1" class="w-full border rounded px-3 py-2 text-sm"></div>
                </div>
                <div style="display:flex;gap:16px;margin-top:10px;font-size:13px;color: var(--ai-text-secondary);">
                    <label><input id="retAudioDelete" type="checkbox" class="mr-1">Auto-delete audio</label>
                    <label><input id="retTranscriptDelete" type="checkbox" class="mr-1">Auto-delete transcripts</label>
                </div>
                <button class="btn btn-primary" style="margin-top:12px;" onclick="saveRetention()"><i class="fas fa-save mr-1"></i>Save Retention</button>
            </div>
            
            <div class="ai-content-card">
                <h3><i class="fas fa-power-off"></i> Voice Kill Switches</h3>
                <div id="killSwitches"></div>
            </div>
            
            <div class="ai-content-card">
                <h3><i class="fas fa-shield-alt"></i> Supported Audio Formats</h3>
                <div id="supportedFormats" style="font-size:13px;color: var(--ai-text-muted);"></div>
            </div>
        </div>
    </div>

<script>
// ===== CONFIG =====
const BASE_URL = window.location.href.split('?')[0];

// ===== TAB NAVIGATION =====
function showPanel(name) {
    document.querySelectorAll('.panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.ai-tab').forEach(t => t.classList.remove('active'));
    document.getElementById('panel-' + name).classList.add('active');
    event.currentTarget.classList.add('active');
    
    if (name === 'history') loadHistory();
    if (name === 'shortcuts') loadShortcuts();
    if (name === 'settings') loadSettings();
    if (name === 'confirmations') loadConfirmations();
    if (name === 'f25tasks') loadVoiceTasks();
}

// ===== AUDIO RECORDING =====
let mediaRecorder = null;
let audioChunks = [];
let recordingStartTime = null;
let timerInterval = null;
let currentTranscriptionId = null;

function toggleRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        stopRecording();
    } else {
        startRecording();
    }
}

async function startRecording() {
    try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        mediaRecorder = new MediaRecorder(stream, { mimeType: 'audio/webm;codecs=opus' });
        audioChunks = [];
        
        mediaRecorder.ondataavailable = (e) => {
            if (e.data.size > 0) audioChunks.push(e.data);
        };
        
        mediaRecorder.onstop = () => {
            stream.getTracks().forEach(t => t.stop());
            onRecordingComplete();
        };
        
        mediaRecorder.start();
        
        const micBtn = document.getElementById('micBtn');
        micBtn.classList.add('recording');
        micBtn.innerHTML = '<i class="fas fa-stop"></i>';
        
        document.getElementById('recorderStatus').textContent = 'Recording... Click to stop';
        document.getElementById('recorderTimer').classList.remove('hidden');
        
        recordingStartTime = Date.now();
        timerInterval = setInterval(updateTimer, 100);
        
        document.getElementById('recorderControls').style.display = 'none';
        document.getElementById('transcriptPreview').classList.add('hidden');
    } catch (err) {
        document.getElementById('recorderStatus').textContent = 'Microphone access denied. Please allow microphone access.';
        console.error('Microphone error:', err);
    }
}

function stopRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        clearInterval(timerInterval);
    }
}

function cancelRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
        clearInterval(timerInterval);
    }
    audioChunks = [];
    resetRecorder();
}

function updateTimer() {
    const elapsed = Math.floor((Date.now() - recordingStartTime) / 1000);
    const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
    const secs = (elapsed % 60).toString().padStart(2, '0');
    document.getElementById('recorderTimer').textContent = mins + ':' + secs;
}

function onRecordingComplete() {
    resetRecorder();
    
    if (audioChunks.length === 0) return;
    
    // Show processing status
    document.getElementById('recorderStatus').textContent = 'Processing audio...';
    document.getElementById('processBtn').disabled = true;
    document.getElementById('recorderControls').style.display = 'flex';
    
    // Client-side transcription using Web Speech API
    const audioBlob = new Blob(audioChunks, { type: 'audio/webm' });
    transcribeAudio(audioBlob);
}

function transcribeAudio(audioBlob) {
    // Use Web Speech API for client-side transcription
    if ('webkitSpeechRecognition' in window || 'SpeechRecognition' in window) {
        const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
        const recognition = new SpeechRecognition();
        recognition.lang = 'en-US';
        recognition.continuous = false;
        recognition.interimResults = false;
        
        recognition.onresult = (event) => {
            const transcript = event.results[0][0].transcript;
            const confidence = event.results[0][0].confidence;
            
            // Create transcription record on server
            uploadAndTranscribe(audioBlob, transcript, confidence);
        };
        
        recognition.onerror = (event) => {
            // Fallback: show manual input
            document.getElementById('recorderStatus').textContent = 'Speech recognition unavailable. Type your command below.';
            document.getElementById('transcriptPreview').classList.remove('hidden');
            document.getElementById('transcriptText').value = '';
            document.getElementById('transcriptText').focus();
            document.getElementById('processBtn').disabled = false;
        };
        
        recognition.onend = () => {
            // Processing complete
        };
        
        recognition.start();
    } else {
        // Browser doesn't support Speech API — show manual input
        document.getElementById('recorderStatus').textContent = 'Speech recognition not supported in this browser. Type your command.';
        document.getElementById('transcriptPreview').classList.remove('hidden');
        document.getElementById('transcriptText').value = '';
        document.getElementById('transcriptText').focus();
        document.getElementById('processBtn').disabled = false;
    }
}

async function uploadAndTranscribe(audioBlob, transcript, confidence) {
    // Convert to base64
    const reader = new FileReader();
    reader.onloadend = async () => {
        const base64Data = reader.result.split(',')[1];
        
        try {
            const formData = new FormData();
            formData.append('action', 'upload_audio');
            formData.append('audio_data', base64Data);
            formData.append('mime_type', audioBlob.type);
            formData.append('duration_ms', Date.now() - recordingStartTime);
            
            const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
            const result = await resp.json();
            
            if (result.success) {
                currentTranscriptionId = result.transcription_id;
                
                // Submit transcription text
                const form2 = new FormData();
                form2.append('action', 'submit_transcription');
                form2.append('transcription_id', currentTranscriptionId);
                form2.append('text', transcript);
                form2.append('confidence', confidence);
                
                const resp2 = await fetch(BASE_URL, { method: 'POST', body: form2 });
                const result2 = await resp2.json();
                
                if (result2.success) {
                    document.getElementById('transcriptText').value = transcript;
                    document.getElementById('transcriptPreview').classList.remove('hidden');
                    document.getElementById('recorderStatus').textContent = 'Transcription ready. Edit if needed, then send.';
                    document.getElementById('processBtn').disabled = false;
                }
            }
        } catch (err) {
            document.getElementById('recorderStatus').textContent = 'Upload failed. Try again.';
            console.error(err);
        }
    };
    reader.readAsDataURL(audioBlob);
}

function resetRecorder() {
    const micBtn = document.getElementById('micBtn');
    micBtn.classList.remove('recording');
    micBtn.innerHTML = '<i class="fas fa-microphone"></i>';
    
    document.getElementById('recorderStatus').textContent = 'Click to start recording';
    document.getElementById('recorderTimer').classList.add('hidden');
    document.getElementById('recorderTimer').textContent = '00:00';
    document.getElementById('recorderControls').style.display = 'none';
}

function retryRecording() {
    document.getElementById('transcriptPreview').classList.add('hidden');
    document.getElementById('transcriptText').value = '';
}

function processRecording() {
    const text = document.getElementById('transcriptText').value.trim();
    if (!text) return;
    
    document.getElementById('transcriptPreview').classList.add('hidden');
    document.getElementById('recorderStatus').textContent = 'Sending command...';
    
    sendVoiceCommand();
}

// ===== VOICE COMMAND =====
async function sendVoiceCommand() {
    const text = document.getElementById('transcriptText').value.trim();
    if (!text) return;
    
    const formData = new FormData();
    formData.append('action', 'send_voice_command');
    formData.append('transcription_id', currentTranscriptionId || 0);
    formData.append('text', text);
    formData.append('context', JSON.stringify({
        page: document.title,
        timestamp: new Date().toISOString()
    }));
    
    try {
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        
        document.getElementById('commandResultCard').classList.remove('hidden');
        const resultDiv = document.getElementById('commandResult');
        
        if (result.type === 'shortcut') {
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#d4edda;border-radius:8px;">
                    <strong><i class="fas fa-bolt"></i> Shortcut Matched</strong>
                    <p style="margin-top:8px;">${result.description || result.action}</p>
                    ${result.url ? `<a href="${escapeHtml(resolveAppUrl(result.url))}" class="btn btn-primary" style="margin-top:8px;">Open</a>` : ''}
                </div>`;
        } else if (result.type === 'navigation') {
            const route = resolveAppUrl(result.route || '');
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#e8f4fd;border-radius:8px;border:1px solid #b8daff;">
                    <strong style="color:#004085;"><i class="fas fa-compass"></i> Navigation</strong>
                    <p style="margin-top:8px;color:#004085;">${escapeHtml(result.message || 'Opening the requested page.')}</p>
                    ${route ? `<a href="${escapeHtml(route)}" class="btn btn-primary" style="margin-top:8px;">${escapeHtml(result.label || 'Open')}</a>` : ''}
                </div>`;
        } else if (result.type === 'pending_confirmation') {
            const preview = result.preview || {};
            const fields = Array.isArray(preview.fields) ? preview.fields.slice(0, 4) : [];
            const warnings = Array.isArray(preview.warnings) ? preview.warnings : [];
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#fff3cd;border-radius:8px;border:1px solid #ffc107;">
                    <strong style="color:#856404;"><i class="fas fa-clipboard-check"></i> Pending Approval</strong>
                    <p style="margin-top:8px;color:#856404;">${escapeHtml(result.message || 'This action requires confirmation.')}</p>
                    <div style="margin-top:10px;display:grid;gap:6px;font-size:13px;">
                        ${fields.map(f => `<div style="display:flex;justify-content:space-between;gap:12px;"><span style="color:#8a6d3b;">${escapeHtml(f.label || '')}:</span><span style="font-weight:600;text-align:right;">${escapeHtml(String(f.value ?? ''))}</span></div>`).join('')}
                    </div>
                    ${warnings.length > 0 ? `<div style="margin-top:10px;display:grid;gap:6px;">${warnings.map(w => `<div style="background:rgba(15, 23, 42, 0.55);border:1px solid rgba(251,191,36,0.30);border-radius:6px;padding:8px;color:#8a6d3b;font-size:12px;">${escapeHtml(w)}</div>`).join('')}</div>` : ''}
                    <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap;">
                        ${result.pending_action_id && result.confirmation_token ? `<button class="btn btn-success" onclick="confirmPendingVoiceAction(${parseInt(result.pending_action_id, 10) || 0}, ${escapeHtml(JSON.stringify(result.confirmation_token || ''))})" style="font-size:12px;"><i class="fas fa-check"></i> Confirm</button>` : ''}
                        ${result.pending_action_id ? `<button class="btn btn-danger" onclick="cancelPendingVoiceAction(${parseInt(result.pending_action_id, 10) || 0})" style="font-size:12px;"><i class="fas fa-times"></i> Cancel</button>` : ''}
                    </div>
                </div>`;
        } else if (result.type === 'governance_denied') {
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#f8d7da;border-radius:8px;border:1px solid #f5c6cb;">
                    <strong style="color:#721c24;"><i class="fas fa-ban"></i> Action Denied</strong>
                    <p style="margin-top:8px;color:#721c24;">${result.error}</p>
                    <p style="margin-top:4px;font-size:12px;color:#856404;">Risk: ${result.risk_level || 'low'}</p>
                </div>`;
        } else if (result.type === 'confirmation_required') {
            const conf = result.confirmation || {};
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#fff3cd;border-radius:8px;border:1px solid #ffc107;">
                    <strong style="color:#856404;"><i class="fas fa-shield-alt"></i> Confirmation Required</strong>
                    <p style="margin-top:8px;">This action requires your approval before execution.</p>
                    <p style="margin-top:4px;font-size:12px;color:#856404;">Risk: ${result.risk_level} | Type: ${conf.action_type || 'unknown'}</p>
                    ${conf.confirmation_key ? `<div style="margin-top:8px;display:flex;gap:8px;">
                        <button class="btn btn-success" onclick="confirmVoiceActionByConfirmKey('${conf.confirmation_key}')" style="font-size:12px;"><i class="fas fa-check"></i> Confirm</button>
                        <button class="btn btn-danger" onclick="rejectVoiceActionByConfirmKey('${conf.confirmation_key}')" style="font-size:12px;"><i class="fas fa-times"></i> Reject</button>
                    </div>` : ''}
                </div>`;
        } else if (result.type === 'intelligence_response') {
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#e8f4fd;border-radius:8px;border:1px solid #b8daff;">
                    <strong style="color:#004085;"><i class="fas fa-lightbulb"></i> Intelligence</strong>
                    <pre style="margin-top:8px;white-space:pre-wrap;font-size:13px;">${escapeHtml(result.response || 'No data')}</pre>
                    ${result.can_create_task ? '<p style="margin-top:8px;font-size:12px;color:#004085;">Say "handle this" to create a task for this intelligence.</p>' : ''}
                </div>`;
        } else if (result.type === 'document_response') {
            resultDiv.innerHTML = `
                <div style="padding:12px;background:#f0f9e8;border-radius:8px;border:1px solid #c3e6cb;">
                    <strong style="color:#155724;"><i class="fas fa-file-alt"></i> Document Answer</strong>
                    <pre style="margin-top:8px;white-space:pre-wrap;font-size:13px;">${escapeHtml(result.response || 'No answer found')}</pre>
                    ${result.needs_document ? '<p style="margin-top:8px;font-size:12px;color:#155724;">Which document would you like to ask about?</p>' : ''}
                </div>`;
        } else if (result.type === 'answer' || result.type === 'analysis' || result.type === 'insight' || result.type === 'knowledge_result' || result.type === 'data_result' || result.type === 'external_data') {
            const items = Array.isArray(result.items) ? result.items : [];
            const itemsHtml = items.length > 0
                ? `<div style="margin-top:10px;display:grid;gap:6px;">${items.map(item => {
                    const text = typeof item === 'string' ? item : JSON.stringify(item);
                    return `<div style="background:rgba(15, 23, 42, 0.55);border:1px solid rgba(56,189,248,0.25);border-radius:6px;padding:8px;font-size:12px;color:#334155;">${escapeHtml(text)}</div>`;
                }).join('')}</div>`
                : '';
            resultDiv.innerHTML = `
                <div style="padding:12px;background: rgba(15, 23, 42, 0.55);border-radius:8px;border:1px solid #dbe3ff;">
                    <strong><i class="fas fa-comment-dots"></i> AI Response</strong>
                    <pre style="margin-top:8px;white-space:pre-wrap;font-size:13px;">${escapeHtml(result.message || result.response || 'No response returned')}</pre>
                    ${itemsHtml}
                </div>`;
        } else if (result.type === 'ai_command') {
            resultDiv.innerHTML = `
                <div style="padding:12px;background: rgba(15, 23, 42, 0.55);border-radius:8px;">
                    <strong><i class="fas fa-robot"></i> AI Command</strong>
                    <p style="margin-top:8px;font-style:italic;">\"${result.text}\"</p>
                    ${result.message ? `<div style="margin-top:8px;padding:10px;background:#fff3cd;border-radius:6px;">
                        <i class="fas fa-exclamation-triangle"></i> ${result.message}
                    </div>` : ''}
                    <p style="margin-top:8px;font-size:12px;color: var(--ai-text-muted);">
                        Risk: ${result.risk_level || 'low'} | 
                        The command will be processed through the normal AI pipeline.
                    </p>
                </div>`;
        } else {
            resultDiv.innerHTML = `<div style="padding:12px;color:red;">${result.error || 'Unknown error'}</div>`;
        }
        
        document.getElementById('recorderStatus').textContent = 'Command sent.';
        document.getElementById('processBtn').disabled = true;
    } catch (err) {
        document.getElementById('recorderStatus').textContent = 'Failed to send command.';
    }
}

// ===== TTS =====
let currentAudio = null;
let currentResponseText = '';

function listenResponse() {
    const text = document.getElementById('aiResponseText').textContent;
    if (!text) return;
    
    currentResponseText = text;
    
    // Try browser TTS first
    if ('speechSynthesis' in window) {
        const utterance = new SpeechSynthesisUtterance(text);
        utterance.onstart = () => {
            document.getElementById('ttsPlayer').style.display = 'flex';
            document.getElementById('listenBtn').innerHTML = '<i class="fas fa-pause"></i> Playing...';
        };
        utterance.onend = () => {
            document.getElementById('listenBtn').innerHTML = '<i class="fas fa-headphones"></i> Listen';
        };
        utterance.onerror = (e) => {
            // F28: TTS failure — text response already visible, no audio
            console.warn('TTS failed:', e.error);
            document.getElementById('listenBtn').innerHTML = '<i class="fas fa-headphones"></i> Listen';
        };
        speechSynthesis.speak(utterance);
    } else {
        // F28: No TTS support — text response is the fallback
        console.warn('Speech synthesis not available. Text response shown above.');
    }
}

function ttsPlay() { listenResponse(); }
function ttsPause() { if ('speechSynthesis' in window) speechSynthesis.pause(); }
function ttsStop() { if ('speechSynthesis' in window) { speechSynthesis.cancel(); document.getElementById('ttsPlayer').style.display = 'none'; document.getElementById('listenBtn').innerHTML = '<i class="fas fa-headphones"></i> Listen'; } }
function ttsReplay() { ttsStop(); listenResponse(); }

// F28: Stop TTS without cancelling the underlying task
function stopSpeaking() {
    if ('speechSynthesis' in window) {
        speechSynthesis.cancel();
    }
    document.getElementById('ttsPlayer').style.display = 'none';
    document.getElementById('listenBtn').innerHTML = '<i class="fas fa-headphones"></i> Listen';
    // Note: This only stops audio output, not the F25 task
}

// ===== DICTATION =====
let dictationRecognition = null;

function startDictation() {
    if (dictationRecognition && dictationRecognition.running) {
        dictationRecognition.stop();
        return;
    }
    
    if (!('webkitSpeechRecognition' in window) && !('SpeechRecognition' in window)) {
        // F28: Fallback to text input when STT not supported
        const text = prompt('Speech recognition is not supported in this browser. Type your dictation text instead:');
        if (text) {
            document.getElementById('dictationTarget').value = text;
        }
        return;
    }
    
    const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;
    dictationRecognition = new SpeechRecognition();
    dictationRecognition.lang = 'en-US';
    dictationRecognition.continuous = true;
    dictationRecognition.interimResults = true;
    
    const target = document.getElementById('dictationTarget');
    const btn = document.getElementById('dictateBtn');
    
    let finalTranscript = target.value;
    
    dictationRecognition.onresult = (event) => {
        let interim = '';
        for (let i = event.resultIndex; i < event.results.length; i++) {
            if (event.results[i].isFinal) {
                finalTranscript += event.results[i][0].transcript + ' ';
            } else {
                interim += event.results[i][0].transcript;
            }
        }
        target.value = finalTranscript + interim;
    };
    
    dictationRecognition.onstart = () => {
        btn.innerHTML = '<i class="fas fa-stop"></i> Stop Dictating';
        btn.className = 'btn btn-danger';
    };
    
    dictationRecognition.onend = () => {
        btn.innerHTML = '<i class="fas fa-microphone"></i> Start Dictating';
        btn.className = 'btn btn-primary';
    };
    
    dictationRecognition.start();
}

function clearDictation() {
    document.getElementById('dictationTarget').value = '';
}

// ===== SHORTCUTS =====
async function loadShortcuts() {
    const formData = new FormData();
    formData.append('action', 'list_shortcuts');
    
    try {
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        
        const list = document.getElementById('shortcutList');
        if (result.shortcuts && result.shortcuts.length > 0) {
            list.innerHTML = result.shortcuts.map(s => `
                <div class="shortcut-card">
                    <div>
                        <div class="command">"${s.command}"</div>
                        <div class="action">${s.description || s.target_action}</div>
                    </div>
                    <button class="btn btn-danger" onclick="deleteShortcut(${s.id})" style="padding:6px 12px;">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<p style="text-align:center;color: var(--ai-text-muted);padding:20px;">No shortcuts configured</p>';
        }
    } catch (err) {
        console.error('Failed to load shortcuts:', err);
    }
}

function showAddShortcut() {
    document.getElementById('addShortcutForm').classList.remove('hidden');
}

function hideAddShortcut() {
    document.getElementById('addShortcutForm').classList.add('hidden');
}

async function saveShortcut() {
    const command = document.getElementById('shortcutCommand').value.trim();
    const action = document.getElementById('shortcutAction').value.trim();
    const url = document.getElementById('shortcutUrl').value.trim();
    const desc = document.getElementById('shortcutDesc').value.trim();
    
    if (!command || !action) {
        alert('Command and Action are required.');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'set_shortcut');
    formData.append('command', command);
    formData.append('target_action', action);
    formData.append('target_url', url);
    formData.append('description', desc);
    
    await fetch(BASE_URL, { method: 'POST', body: formData });
    hideAddShortcut();
    loadShortcuts();
}

async function deleteShortcut(id) {
    const formData = new FormData();
    formData.append('action', 'delete_shortcut');
    formData.append('shortcut_id', id);
    
    await fetch(BASE_URL, { method: 'POST', body: formData });
    loadShortcuts();
}

// ===== HISTORY =====
async function loadHistory() {
    const formData = new FormData();
    formData.append('action', 'get_transcriptions');
    formData.append('limit', '20');
    
    try {
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        
        const list = document.getElementById('historyList');
        if (result.transcriptions && result.transcriptions.length > 0) {
            list.innerHTML = result.transcriptions.map(t => `
                <div class="history-item">
                    <div>
                        <div class="history-text">${escapeHtml(t.transcript_preview || '(no text)')}</div>
                        <div class="history-meta">
                            ${t.provider} | ${t.detected_language || t.language || 'en'} | 
                            ${(t.duration_ms/1000).toFixed(1)}s | 
                            ${new Date(t.created_at).toLocaleString()}
                        </div>
                    </div>
                    <span class="status-badge status-${t.status}">${t.status}</span>
                </div>
            `).join('');
        } else {
            list.innerHTML = '<div style="text-align:center;padding:40px;color: var(--ai-text-muted);"><i class="fas fa-microphone" style="font-size:48px;margin-bottom:12px;display:block;"></i>No voice recordings yet</div>';
        }
    } catch (err) {
        console.error('Failed to load history:', err);
    }
}

// ===== SETTINGS =====
async function loadSettings() {
    const formData = new FormData();
    formData.append('action', 'get_dashboard');
    
    try {
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        
        if (result.dashboard) {
            const d = result.dashboard;
            
            // Stats
            const statsGrid = document.getElementById('statsGrid');
            const trans = d.stats?.transcriptions || {};
            statsGrid.innerHTML = `
                <div class="stat-card">
                    <div class="value">${trans.total || 0}</div>
                    <div class="label">Total Transcriptions</div>
                </div>
                <div class="stat-card">
                    <div class="value">${trans.completed || 0}</div>
                    <div class="label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="value">${trans.failed || 0}</div>
                    <div class="label">Failed</div>
                </div>
                <div class="stat-card">
                    <div class="value">${((trans.total_duration_ms || 0) / 60000).toFixed(1)}m</div>
                    <div class="label">Total Duration</div>
                </div>
                <div class="stat-card">
                    <div class="value">${d.stats?.failure_rate || 0}%</div>
                    <div class="label">Failure Rate</div>
                </div>
                <div class="stat-card">
                    <div class="value">${d.stats?.stt_30d?.requests || 0}</div>
                    <div class="label">STT (30 days)</div>
                </div>
                <div class="stat-card">
                    <div class="value">${d.stats?.tts_30d?.requests || 0}</div>
                    <div class="label">TTS (30 days)</div>
                </div>
            `;

            const providerDiv = document.getElementById('providerConfigs');
            const providerResp = await fetch(BASE_URL, { method: 'POST', body: (() => { const f = new FormData(); f.append('action','get_providers'); return f; })() });
            const providerResult = await providerResp.json();
            if (providerResult.providers && providerResult.providers.length) {
                providerDiv.innerHTML = providerResult.providers.map(p => `
                    <div class="p-3 rounded-lg border border-gray-100 bg-gray-50">
                        <div class="flex items-center justify-between gap-2">
                            <div>
                                <div class="font-semibold text-sm text-gray-800">${escapeHtml(p.provider)} <span class="text-xs text-gray-500">(${escapeHtml(p.provider_type)})</span></div>
                                <div class="text-xs text-gray-500">Priority ${p.priority} · ${p.is_active ? 'Active' : 'Inactive'}</div>
                            </div>
                            <button class="btn btn-secondary" style="padding:6px 10px;" onclick="toggleProviderActive('${escapeHtml(p.provider_type)}','${escapeHtml(p.provider)}', ${p.is_active ? 0 : 1})">${p.is_active ? 'Disable' : 'Enable'}</button>
                        </div>
                    </div>
                `).join('');
            } else {
                providerDiv.innerHTML = '<p style="font-size:13px;color: var(--ai-text-muted);">No explicit providers configured. Browser fallback is active.</p>';
            }

            const retentionResp = await fetch(BASE_URL, { method: 'POST', body: (() => { const f = new FormData(); f.append('action','get_retention'); return f; })() });
            const retentionResult = await retentionResp.json();
            if (retentionResult.retention) {
                const ret = retentionResult.retention;
                document.getElementById('retAudio').value = ret.audio_retention_days ?? 7;
                document.getElementById('retTranscript').value = ret.transcript_retention_days ?? 90;
                document.getElementById('retTts').value = ret.tts_cache_retention_days ?? 1;
                document.getElementById('retMeta').value = ret.metadata_retention_days ?? 365;
                document.getElementById('retAudioDelete').checked = !!Number(ret.auto_delete_audio ?? 1);
                document.getElementById('retTranscriptDelete').checked = !!Number(ret.auto_delete_transcripts ?? 0);
            }
            
            // Kill Switches
            const ksDiv = document.getElementById('killSwitches');
            const switches = d.kill_switches || {};
            const labels = {
                voice_input: 'Voice Input',
                stt: 'Speech-to-Text',
                tts: 'Text-to-Speech',
                dictation: 'Voice Dictation',
                voice_commands: 'Voice Commands'
            };
            ksDiv.innerHTML = Object.entries(switches).map(([key, enabled]) => `
                <div class="kill-switch-row">
                    <span>${labels[key] || key}</span>
                    <button class="toggle ${enabled ? 'on' : ''}" onclick="toggleKillSwitch(this, '${key}', ${enabled ? 1 : 0})"></button>
                </div>
            `).join('');
            
            // Supported formats
            const fmtDiv = document.getElementById('supportedFormats');
            const fmtResp = await fetch(BASE_URL, { method: 'POST', body: (() => { const f = new FormData(); f.append('action','get_supported_formats'); return f; })() });
            const fmtResult = await fmtResp.json();
            if (fmtResult.formats) {
                fmtDiv.innerHTML = `
                    <p><strong>Audio Formats:</strong> ${fmtResult.formats.mime_types.join(', ')}</p>
                    <p><strong>Max File Size:</strong> ${(fmtResult.formats.max_file_size_bytes / 1024 / 1024).toFixed(0)} MB</p>
                    <p><strong>Max Duration:</strong> ${(fmtResult.formats.max_duration_ms / 1000).toFixed(0)} seconds</p>
                    <p><strong>Min Duration:</strong> ${(fmtResult.formats.min_duration_ms / 1000).toFixed(1)} seconds</p>
                `;
            }
        }
    } catch (err) {
        console.error('Failed to load settings:', err);
    }
}

    async function toggleKillSwitch(btn, feature, currentEnabled) {
        const nextEnabled = currentEnabled ? 0 : 1;
        const formData = new FormData();
        formData.append('action', 'toggle_kill_switch');
        formData.append('feature', feature);
        formData.append('enabled', nextEnabled);
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            btn.classList.toggle('on', !!nextEnabled);
        } else {
            alert(result.error || 'Failed to update kill switch.');
        }
    }

    async function toggleProviderActive(providerType, provider, nextActive) {
        const formData = new FormData();
        formData.append('action', 'save_provider');
        formData.append('provider_type', providerType);
        formData.append('provider', provider);
        formData.append('is_active', nextActive ? '1' : '0');
        formData.append('priority', '0');
        formData.append('config_data', '{}');
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        if (result.success) {
            loadSettings();
        } else {
            alert(result.error || 'Failed to update provider.');
        }
    }

    async function saveRetention() {
        const formData = new FormData();
        formData.append('action', 'save_retention');
        formData.append('audio_retention_days', document.getElementById('retAudio').value || '7');
        formData.append('transcript_retention_days', document.getElementById('retTranscript').value || '90');
        formData.append('tts_cache_retention_days', document.getElementById('retTts').value || '1');
        formData.append('metadata_retention_days', document.getElementById('retMeta').value || '365');
        formData.append('auto_delete_audio', document.getElementById('retAudioDelete').checked ? '1' : '0');
        formData.append('auto_delete_transcripts', document.getElementById('retTranscriptDelete').checked ? '1' : '0');
        const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
        const result = await resp.json();
        if (!result.success) {
            alert(result.error || 'Failed to save retention policy.');
        }
    }

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function resolveAppUrl(route) {
    const value = (route || '').toString().trim();
    if (!value) return '';
    if (/^https?:\/\//i.test(value)) return value;
    const base = BASE_URL.replace(/\/+$/, '');
    return `${base}/${value.replace(/^\/+/, '')}`;
}

function renderVoiceExecutionResult(result) {
    if (typeof result === 'string') {
        return `
            <div style="margin-top:10px;padding:10px;background: rgba(15, 23, 42, 0.55);border-radius:6px;border:1px solid #dbe3ff;">
                <div style="font-weight:600;"><i class="fas fa-circle-check"></i> Executed</div>
                <div style="margin-top:6px;">${escapeHtml(result)}</div>
            </div>`;
    }

    if (!result) return '';

    if (result.success === false) {
        return `
            <div style="margin-top:10px;padding:10px;background:#f8d7da;border-radius:6px;border:1px solid #f5c6cb;color:#721c24;">
                <div style="font-weight:600;"><i class="fas fa-triangle-exclamation"></i> Execution Failed</div>
                <div style="margin-top:6px;">${escapeHtml(result.error || 'The command could not be completed.')}</div>
            </div>`;
    }

    if (result.type === 'navigation' && result.route) {
        const route = resolveAppUrl(result.route);
        return `
            <div style="margin-top:10px;padding:10px;background:#e8f4fd;border-radius:6px;border:1px solid #b8daff;">
                <div style="font-weight:600;color:#004085;"><i class="fas fa-compass"></i> ${escapeHtml(result.title || 'Navigation')}</div>
                <p style="margin-top:6px;color:#004085;">${escapeHtml(result.message || 'Opening the requested page.')}</p>
                <a href="${escapeHtml(route)}" class="btn btn-primary" style="margin-top:8px;">${escapeHtml(result.label || 'Open')}</a>
            </div>`;
    }

    if (result.type === 'pending_confirmation') {
        return `
            <div style="margin-top:10px;padding:10px;background:#fff3cd;border-radius:6px;border:1px solid #ffc107;color:#856404;">
                <strong><i class="fas fa-clipboard-check"></i> Pending Confirmation</strong>
                <div style="margin-top:6px;">${escapeHtml(result.message || 'This action still needs approval.')}</div>
            </div>`;
    }

    return `
        <div style="margin-top:10px;padding:10px;background: rgba(15, 23, 42, 0.55);border-radius:6px;border:1px solid #dbe3ff;">
            <div style="font-weight:600;"><i class="fas fa-circle-check"></i> Executed</div>
            <div style="margin-top:6px;">${escapeHtml(result.message || 'The command was processed successfully.')}</div>
        </div>`;
}

async function confirmPendingVoiceAction(pendingId, token) {
    if (!pendingId || !token) return;

    const formData = new FormData();
    formData.append('action', 'confirm');
    formData.append('pending_action_id', pendingId);
    formData.append('token', token);

    try {
        const resp = await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=confirm`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({
                pending_action_id: pendingId,
                token: token
            })
        });
        const result = await resp.json();
        const resultDiv = document.getElementById('commandResult');
        if (result.success) {
            resultDiv.innerHTML = renderVoiceExecutionResult(result.data || result.execution_result || result);
        } else {
            resultDiv.innerHTML = `<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;">${escapeHtml(result.error || 'Confirmation failed')}</div>`;
        }
    } catch (err) {
        const resultDiv = document.getElementById('commandResult');
        resultDiv.innerHTML = `<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;">Confirmation failed.</div>`;
    }
}

async function cancelPendingVoiceAction(pendingId) {
    if (!pendingId) return;

    try {
        await fetch(`${BASE_URL}/modules/ai/api/actions.php?action=cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify({ pending_action_id: pendingId })
        });
        const resultDiv = document.getElementById('commandResult');
        resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;"><i class="fas fa-times-circle"></i> <strong>Action Cancelled</strong></div>';
    } catch (err) {
        const resultDiv = document.getElementById('commandResult');
        resultDiv.innerHTML = '<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;">Cancellation failed.</div>';
    }
}

// ===== F28: CONVERSATIONS =====
let currentSessionId = null;

async function startVoiceSession() {
    const contextType = document.getElementById('convContextType').value;
    const formData = new FormData();
    formData.append('action', 'start_voice_session');
    formData.append('context_type', contextType);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success && result.session_id) {
        currentSessionId = result.session_id;
        document.getElementById('activeSession').style.display = 'block';
        document.getElementById('sessionMessages').innerHTML = '<div style="color:#667eea;font-size:13px;"><i class="fas fa-info-circle"></i> Session started (' + contextType + ')</div>';
    }
}

async function sendSessionMessage() {
    const input = document.getElementById('sessionInput');
    const msg = input.value.trim();
    if (!msg || !currentSessionId) return;
    input.value = '';
    const msgsDiv = document.getElementById('sessionMessages');
    msgsDiv.innerHTML += '<div style="margin-bottom:8px;text-align:right;"><span style="background:#667eea;color:white;padding:8px 12px;border-radius:12px;display:inline-block;max-width:80%;text-align:left;">' + escapeHtml(msg) + '</span></div>';
    // Send to server
    const formData = new FormData();
    formData.append('action', 'add_voice_message');
    formData.append('session_id', currentSessionId);
    formData.append('role', 'user');
    formData.append('content', msg);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success && result.response) {
        msgsDiv.innerHTML += '<div style="margin-bottom:8px;"><span style="background:#f0f0f0;padding:8px 12px;border-radius:12px;display:inline-block;max-width:80%;font-size:13px;">' + escapeHtml(result.response) + '</span></div>';
    }
    msgsDiv.scrollTop = msgsDiv.scrollHeight;
}

async function endVoiceSession() {
    if (!currentSessionId) return;
    const formData = new FormData();
    formData.append('action', 'end_voice_session');
    formData.append('session_id', currentSessionId);
    await fetch(BASE_URL, { method: 'POST', body: formData });
    currentSessionId = null;
    document.getElementById('activeSession').style.display = 'none';
}

// ===== F28: CONFIRMATIONS =====
async function loadConfirmations() {
    const formData = new FormData();
    formData.append('action', 'get_voice_confirmations');
    formData.append('status', 'pending');
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    const div = document.getElementById('pendingConfirmations');
    if (result.success && result.confirmations.length > 0) {
        div.innerHTML = result.confirmations.map(c => {
            let params = {};
            try {
                params = c.parameters || (c.parameters_json ? JSON.parse(c.parameters_json) : {});
            } catch (err) {
                params = {};
            }
            const commandText = params.text || c.command_text || c.action_type || 'Voice action';
            const contextLabel = params.context && params.context.page ? params.context.page : '';
            return '<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:8px;padding:12px;margin-bottom:12px;" id="conf-' + c.id + '">' +
            '<div style="font-weight:600;">' + escapeHtml(commandText) + '</div>' +
            (contextLabel ? '<div style="font-size:12px;color:#856404;margin-top:4px;">Context: ' + escapeHtml(contextLabel) + '</div>' : '') +
            '<div style="font-size:12px;color: var(--ai-text-muted);margin-top:4px;">Action: ' + escapeHtml(c.action_type) + ' | Risk: ' + escapeHtml(c.risk_level) + '</div>' +
            '<div style="margin-top:8px;display:flex;gap:8px;">' +
            '<button class="btn btn-success" onclick="confirmVoiceAction(' + c.id + ')" style="font-size:12px;padding:6px 12px;"><i class="fas fa-check"></i> Confirm</button>' +
            '<button class="btn btn-danger" onclick="rejectVoiceAction(' + c.id + ')" style="font-size:12px;padding:6px 12px;"><i class="fas fa-times"></i> Reject</button>' +
            '</div></div>';
        }).join('');
    } else {
        div.innerHTML = '<div style="text-align:center;padding:30px;color: var(--ai-text-muted);">No pending confirmations</div>';
    }
}

async function confirmVoiceAction(confirmId) {
    const formData = new FormData();
    formData.append('action', 'confirm_voice_action');
    formData.append('confirmation_id', confirmId);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
if (result.success) {
        const el = document.getElementById('conf-' + confirmId);
        if (el) {
            el.style.background = '#d4edda';
            el.style.borderColor = '#28a745';
            el.querySelector('div:last-child').innerHTML = renderVoiceExecutionResult(result.execution_result || result.data || result);
        }
    }
}

async function rejectVoiceAction(confirmId) {
    const reason = prompt('Reason for rejection (optional):') || '';
    const formData = new FormData();
    formData.append('action', 'reject_voice_action');
    formData.append('confirmation_id', confirmId);
    formData.append('reason', reason);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success) {
        const el = document.getElementById('conf-' + confirmId);
        if (el) {
            el.style.background = '#f8d7da';
            el.style.borderColor = '#dc3545';
            el.querySelector('div:last-child').innerHTML = '<span style="color:#721c24;"><i class="fas fa-times-circle"></i> Rejected</span>';
        }
    }
}

// F28: Confirmation by key (from inline buttons in voice result)
async function confirmVoiceActionByConfirmKey(confirmKey) {
    const formData = new FormData();
    formData.append('action', 'confirm_voice_action');
    formData.append('confirmation_key', confirmKey);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
if (result.success) {
        document.getElementById('commandResult').innerHTML = renderVoiceExecutionResult(result.execution_result || result.data || result);
    } else {
        document.getElementById('commandResult').innerHTML = '<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;">' + (result.error || 'Confirmation failed') + '</div>';
    }
}

async function rejectVoiceActionByConfirmKey(confirmKey) {
    const formData = new FormData();
    formData.append('action', 'reject_voice_action');
    formData.append('confirmation_key', confirmKey);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success) {
        document.getElementById('commandResult').innerHTML = '<div style="padding:12px;background:#f8d7da;border-radius:8px;"><i class="fas fa-times-circle" style="color:#721c24;"></i> <strong style="color:#721c24;">Action Rejected</strong></div>';
    } else {
        document.getElementById('commandResult').innerHTML = '<div style="padding:12px;background:#f8d7da;border-radius:8px;color:#721c24;">' + (result.error || 'Rejection failed') + '</div>';
    }
}

// ===== F28: F25 AGENT TASKS =====
async function createVoiceAgentTask() {
    const desc = document.getElementById('f25TaskDesc').value.trim();
    if (!desc) return;
    const formData = new FormData();
    formData.append('action', 'create_agent_task_from_voice');
    formData.append('description', desc);
    formData.append('priority', document.getElementById('f25TaskPriority').value);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success) {
        document.getElementById('f25TaskDesc').value = '';
        loadVoiceTasks();
    } else {
        alert(result.error || 'Failed to create task');
    }
}

async function loadVoiceTasks() {
    const formData = new FormData();
    formData.append('action', 'list_tasks');
    const resp = await fetch(BASE_URL.replace('voice', 'api/agents') + '?action=list_tasks', { method: 'POST' });
    const result = await resp.json();
    const div = document.getElementById('voiceTaskList');
    if (result.success && result.tasks) {
        div.innerHTML = result.tasks.slice(0, 10).map(t =>
            '<div style="background: rgba(15, 23, 42, 0.55);border-radius:8px;padding:10px;margin-bottom:8px;border-left:4px solid ' + (t.priority === 'urgent' ? '#dc3545' : t.priority === 'high' ? '#fd7e14' : '#667eea') + ';" >' +
            '<div style="font-size:13px;font-weight:500;">' + escapeHtml(t.description) + '</div>' +
            '<div style="font-size:11px;color: var(--ai-text-muted);margin-top:2px;">Status: ' + t.status + ' | Priority: ' + t.priority + '</div>' +
            '</div>'
        ).join('');
    }
}

// ===== F28: F26 INTELLIGENCE =====
async function createVoiceInsight() {
    const insight = document.getElementById('f26Insight').value.trim();
    if (!insight) return;
    const formData = new FormData();
    formData.append('action', 'create_intelligence_from_voice');
    formData.append('command', 'voice_insight');
    formData.append('insight', insight);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success) {
        document.getElementById('f26Insight').value = '';
        alert('Intelligence event created.');
    }
}

async function addVoiceWatchlist() {
    const entityType = document.getElementById('watchEntity').value.trim();
    const entityId = document.getElementById('watchEntityId').value;
    const condition = document.getElementById('watchCondition').value.trim();
    if (!entityType || !entityId || !condition) return alert('All fields required.');
    const formData = new FormData();
    formData.append('action', 'voice_watchlist_add');
    formData.append('entity_type', entityType);
    formData.append('entity_id', entityId);
    formData.append('condition', condition);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    if (result.success) {
        document.getElementById('watchEntity').value = '';
        document.getElementById('watchEntityId').value = '';
        document.getElementById('watchCondition').value = '';
        alert('Added to watchlist.');
    }
}

// ===== F28: F27 DOCUMENTS =====
async function askDocByVoice() {
    const docId = document.getElementById('f27DocId').value;
    const question = document.getElementById('f27Question').value.trim();
    if (!docId || !question) return;
    const formData = new FormData();
    formData.append('action', 'ask_document_by_voice');
    formData.append('document_id', docId);
    formData.append('question', question);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    const div = document.getElementById('docAnswer');
    div.style.display = 'block';
    if (result.success) {
        div.innerHTML = '<div class="ai-content-card"><div style="font-size:14px;">' + escapeHtml(result.answer || 'No answer found.') + '</div>' +
        (result.sources ? '<div style="font-size:12px;color: var(--ai-text-muted);margin-top:8px;">Sources: ' + result.sources.map(s => escapeHtml(s)).join(', ') + '</div>' : '') + '</div>';
    } else {
        div.innerHTML = '<div style="color:#e74c3c;">' + escapeHtml(result.error || 'No answer found.') + '</div>';
    }
}

async function voiceDocSearch() {
    const query = document.getElementById('f27SearchQuery').value.trim();
    if (!query) return;
    const formData = new FormData();
    formData.append('action', 'voice_document_search');
    formData.append('query', query);
    const resp = await fetch(BASE_URL, { method: 'POST', body: formData });
    const result = await resp.json();
    const div = document.getElementById('docSearchResults');
    if (result.success && result.documents && result.documents.length > 0) {
        div.innerHTML = result.documents.map(d =>
            '<div style="background: rgba(15, 23, 42, 0.55);border-radius:8px;padding:10px;margin-bottom:8px;" >' +
            '<div style="font-size:13px;font-weight:500;">' + escapeHtml(d.filename || d.title || 'Document') + ' <span style="color:#667eea;font-size:11px;">(ID: ' + d.id + ')</span></div>' +
            '<div style="font-size:12px;color: var(--ai-text-muted);margin-top:2px;">' + escapeHtml(d.document_type || 'Unknown type') + ' | ' + escapeHtml(d.created_at || '') + '</div>' +
            '</div>'
        ).join('');
    } else {
        div.innerHTML = '<div style="text-align:center;padding:20px;color: var(--ai-text-muted);">No documents found</div>';
    }
}
</script>
    <?php ai_render_chat_launcher(); ?>
</body>
</html>





