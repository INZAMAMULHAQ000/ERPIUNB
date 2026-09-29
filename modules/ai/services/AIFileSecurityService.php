<?php
/**
 * AI F24 — File Security Service.
 *
 * Enforces file and code execution boundaries.
 * AI must not execute uploaded files, generate executable server files,
 * access arbitrary filesystem paths, or read unrelated server directories.
 *
 * Covers Sections 31 (File Security) and 32 (Code Execution Boundary).
 */
class AIFileSecurityService
{
    // Blocked file extensions that must never be executed
    private static array $blockedExtensions = [
        'php', 'phtml', 'php3', 'php4', 'php5', 'php7', 'php8',
        'sh', 'bash', 'zsh', 'csh', 'ksh',
        'bat', 'cmd', 'com', 'exe', 'msi',
        'py', 'rb', 'pl', 'cgi', 'fcgi',
        'js', 'jsp', 'asp', 'aspx', 'cfm',
    ];

    // Patterns that indicate file system access attempts
    private static array $filesystemPatterns = [
        '/\.\.\//',           // Path traversal
        '/\.\.\\\\/',         // Windows path traversal
        '/%2e%2e/i',          // Encoded traversal
        '/~\//',              // Home directory
        '/\/etc\//',          // System files
        '/\/var\//',          // System files
        '/\/usr\//',          // System files
        '/\/proc\//',         // Process files
        '/\/sys\//',          // System files
        '/\/tmp\//',          // Temp directory
        '/\/dev\//',          // Device files
    ];

    // Blocked content types for execution
    private static array $executableMimeTypes = [
        'application/x-executable',
        'application/x-php',
        'application/x-httpd-php',
        'application/x-sh',
        'text/x-script',
        'text/x-perl',
        'text/x-python',
    ];

    /**
     * Validate a file upload is safe for AI processing.
     *
     * @param string $filename Original filename
     * @param string $mimeType MIME type
     * @param int    $size     File size in bytes
     * @return array Validation result
     */
    public static function validateUpload(string $filename, string $mimeType = '', int $size = 0): array
    {
        $result = [
            'safe' => true,
            'errors' => [],
            'warnings' => [],
        ];

        // Check file extension
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, self::$blockedExtensions)) {
            $result['safe'] = false;
            $result['errors'][] = "File type '.$extension' is not permitted for AI processing";
            return $result;
        }

        // Check MIME type
        if (!empty($mimeType) && in_array(strtolower($mimeType), self::$executableMimeTypes)) {
            $result['safe'] = false;
            $result['errors'][] = "File MIME type '$mimeType' is not permitted";
            return $result;
        }

        // Check for path traversal in filename
        if (preg_match('/\.\.[\/\\\\]/', $filename) || str_contains($filename, '..')) {
            $result['safe'] = false;
            $result['errors'][] = 'Filename contains path traversal patterns';
            return $result;
        }

        // Check for null bytes
        if (str_contains($filename, "\0")) {
            $result['safe'] = false;
            $result['errors'][] = 'Filename contains null bytes';
            return $result;
        }

        return $result;
    }

    /**
     * Validate that a file path is within allowed directories.
     *
     * @param string $filePath The file path to check
     * @return array Validation result
     */
    public static function validateFilePath(string $filePath): array
    {
        $result = [
            'safe' => true,
            'errors' => [],
        ];

        // Check for filesystem access patterns
        foreach (self::$filesystemPatterns as $pattern) {
            if (preg_match($pattern, $filePath)) {
                $result['safe'] = false;
                $result['errors'][] = 'File path contains restricted filesystem patterns';
                return $result;
            }
        }

        // Check for absolute paths to system directories
        $systemPaths = ['/etc/', '/var/', '/usr/', '/proc/', '/sys/', '/dev/', 'C:\\Windows', 'C:\\System'];
        foreach ($systemPaths as $sysPath) {
            if (str_starts_with($filePath, $sysPath) || str_contains($filePath, $sysPath)) {
                $result['safe'] = false;
                $result['errors'][] = 'File path accesses restricted system directory';
                return $result;
            }
        }

        return $result;
    }

    /**
     * Check if AI-generated code is safe for text/preview only (not execution).
     * Implements Section 32: Code Execution Boundary.
     *
     * @param string $code The generated code
     * @return array Safety result
     */
    public static function validateGeneratedCode(string $code): array
    {
        $result = [
            'safe_for_preview' => true,
            'warnings' => [],
            'dangerous_patterns' => [],
        ];

        // Check for dangerous code patterns
        $dangerousPatterns = [
            ['pattern' => '/exec\s*\(/i', 'name' => 'exec() call', 'severity' => 'critical'],
            ['pattern' => '/system\s*\(/i', 'name' => 'system() call', 'severity' => 'critical'],
            ['pattern' => '/shell_exec\s*\(/i', 'name' => 'shell_exec() call', 'severity' => 'critical'],
            ['pattern' => '/passthru\s*\(/i', 'name' => 'passthru() call', 'severity' => 'critical'],
            ['pattern' => '/proc_open\s*\(/i', 'name' => 'proc_open() call', 'severity' => 'critical'],
            ['pattern' => '/popen\s*\(/i', 'name' => 'popen() call', 'severity' => 'critical'],
            ['pattern' => '/eval\s*\(/i', 'name' => 'eval() call', 'severity' => 'critical'],
            ['pattern' => '/file_get_contents\s*\(\s*[\'"]https?:\/\//i', 'name' => 'external HTTP request', 'severity' => 'high'],
            ['pattern' => '/file_put_contents\s*\(/i', 'name' => 'file write', 'severity' => 'high'],
            ['pattern' => '/unlink\s*\(/i', 'name' => 'file deletion', 'severity' => 'high'],
            ['pattern' => '/rmdir\s*\(/i', 'name' => 'directory deletion', 'severity' => 'high'],
            ['pattern' => '/chmod\s*\(/i', 'name' => 'permission change', 'severity' => 'high'],
            ['pattern' => '/chown\s*\(/i', 'name' => 'ownership change', 'severity' => 'high'],
        ];

        foreach ($dangerousPatterns as $dp) {
            if (preg_match($dp['pattern'], $code)) {
                $result['dangerous_patterns'][] = $dp['name'];
                $result['warnings'][] = "Generated code contains {$dp['name']} — preview only, do not execute";
            }
        }

        return $result;
    }

    /**
     * Ensure file access uses registered document/file services only.
     */
    public static function isRegisteredFileService(string $serviceName): bool
    {
        $registered = [
            'AIDocumentService',
            'AIDocumentPipelineService',
            'AIDocumentExtractionService',
            'AIDocumentTemplateService',
            'AIDocumentComparisonService',
            'AIDocumentSearchService',
            'AIDocumentIntelligenceService',
        ];

        return in_array($serviceName, $registered);
    }

    /**
     * Get allowed file extensions for AI document processing.
     */
    public static function getAllowedExtensions(): array
    {
        return [
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
            'txt', 'csv', 'tsv', 'rtf', 'md',
            'png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff',
            'html', 'xml', 'json',
        ];
    }
}
