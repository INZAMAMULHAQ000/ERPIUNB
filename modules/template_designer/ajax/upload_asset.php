<?php
/**
 * Template Designer — Asset Upload Endpoint
 * Handles file uploads for template assets (logos, backgrounds, watermarks, etc.)
 */
session_start();
$basePath = dirname(__DIR__, 3);
require_once $basePath . '/config/database.php';
require_once $basePath . '/helpers/security.php';
require_once $basePath . '/helpers/rbac.php';
require_once $basePath . '/helpers/redirect.php';
require_once $basePath . '/middleware/auth.php';
require_once $basePath . '/modules/template_designer/components/template_sanitizer.php';

requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

if (!hasPermission('template_designer.edit')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$templateId = (int)($_POST['template_id'] ?? 0);
if (!$templateId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Template ID required']);
    exit;
}

$assetType = $_POST['asset_type'] ?? 'image';
$allowedTypes = ['logo', 'background', 'watermark', 'image', 'stamp', 'signature', 'barcode', 'qrcode'];
if (!in_array($assetType, $allowedTypes)) {
    $assetType = 'image';
}

// Validate template exists
$db = getDB();
$stmt = $db->prepare("SELECT id FROM templates WHERE id = ?");
$stmt->execute([$templateId]);
if (!$stmt->fetch()) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Template not found']);
    exit;
}

// Validate upload
if (empty($_FILES['asset'])) {
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$error = validateAssetUpload($_FILES['asset']);
if ($error) {
    echo json_encode(['success' => false, 'error' => $error]);
    exit;
}

try {
    // Ensure upload directory exists
    $uploadDir = $basePath . '/assets/uploads/templates/' . $templateId;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $file = $_FILES['asset'];
    $originalName = sanitizeFilename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $storageName = uniqid('asset_') . '_' . time() . '.' . $ext;
    $destPath = $uploadDir . '/' . $storageName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new Exception('Failed to save uploaded file');
    }

    $relativePath = 'assets/uploads/templates/' . $templateId . '/' . $storageName;

    // Insert asset record
    $stmt = $db->prepare("
        INSERT INTO template_assets (template_id, asset_type, asset_name, file_path, file_size, mime_type)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $templateId,
        $assetType,
        $originalName,
        $relativePath,
        $file['size'],
        mime_content_type($destPath),
    ]);

    $assetId = $db->lastInsertId();

    echo json_encode([
        'success' => true,
        'asset' => [
            'id' => $assetId,
            'name' => $originalName,
            'path' => $relativePath,
            'url' => '/' . $relativePath,
            'type' => $assetType,
            'size' => $file['size'],
        ],
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}