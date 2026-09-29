<?php
/**
 * AI F11 — Text Extractor.
 *
 * Extracts text from supported file formats.
 */

class AITextExtractor
{
    /**
     * Extract text from a file.
     *
     * @param string $filePath Path to the file
     * @param string $ext File extension
     * @return string|null Extracted text or null on failure
     */
    public static function extract(string $filePath, string $ext): ?string
    {
        if (!file_exists($filePath)) return null;

        return match(strtolower($ext)) {
            'txt', 'md' => self::extractPlainText($filePath),
            'pdf' => self::extractPDF($filePath),
            'docx' => self::extractDOCX($filePath),
            default => null,
        };
    }

    private static function extractPlainText(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) return null;

        // Detect encoding
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }

        return $content;
    }

    private static function extractPDF(string $filePath): ?string
    {
        // Try pdftotext if available (poppler-utils)
        $pdftotext = trim(shell_exec('which pdftotext 2>/dev/null') ?? '');

        if ($pdftotext !== '') {
            $output = shell_exec("{$pdftotext} " . escapeshellarg($filePath) . " - 2>/dev/null");
            if ($output !== null && trim($output) !== '') {
                return $output;
            }
        }

        // Fallback: try to read raw text from PDF (basic extraction)
        $content = file_get_contents($filePath);
        if ($content === false) return null;

        // Extract text between stream objects (very basic)
        $text = '';
        if (preg_match_all('/BT\s*(.*?)\s*ET/s', $content, $matches)) {
            foreach ($matches[1] as $match) {
                if (preg_match_all('/\((.*?)\)/', $match, $textMatches)) {
                    foreach ($textMatches[1] as $t) {
                        $text .= $t . ' ';
                    }
                }
            }
        }

        return trim($text) !== '' ? $text : null;
    }

    private static function extractDOCX(string $filePath): ?string
    {
        // DOCX is a ZIP file containing XML
        $zip = new ZipArchive();
        if ($zip->open($filePath) !== true) return null;

        $xmlContent = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xmlContent === false) return null;

        // Strip XML tags to get plain text
        $text = strip_tags($xmlContent);
        // Decode XML entities
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text) !== '' ? trim($text) : null;
    }
}

/**
 * AI F11 — Text Chunker.
 *
 * Splits text into overlapping chunks for embedding and retrieval.
 */
class AIChunker
{
    /**
     * Split text into chunks.
     *
     * @param string $text Full text
     * @param int $chunkSize Target chunk size in characters
     * @param int $overlap Overlap between chunks
     * @return array Array of text chunks
     */
    public static function chunk(string $text, int $chunkSize = 1000, int $overlap = 200): array
    {
        if (strlen($text) <= $chunkSize) {
            return [$text];
        }

        $chunks = [];
        $start = 0;
        $textLen = strlen($text);

        while ($start < $textLen) {
            $end = min($start + $chunkSize, $textLen);

            // Try to break at a semantic boundary (paragraph, sentence)
            if ($end < $textLen) {
                $breakPoint = self::findBreakPoint($text, $start, $end);
                if ($breakPoint > $start) {
                    $end = $breakPoint;
                }
            }

            $chunk = trim(substr($text, $start, $end - $start));
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }

            // Move forward with overlap
            $start = $end - $overlap;
            if ($start <= ($end - ($chunkSize - $overlap))) {
                $start = $end; // Prevent infinite loop
            }
        }

        return $chunks;
    }

    /**
     * Find the best break point within a range.
     */
    private static function findBreakPoint(string $text, int $start, int $end): int
    {
        // Look for paragraph break
        $pos = strrpos(substr($text, $start, $end - $start), "\n\n");
        if ($pos !== false) return $start + $pos + 2;

        // Look for sentence break
        $pos = strrpos(substr($text, $start, $end - $start), '. ');
        if ($pos !== false) return $start + $pos + 2;

        // Look for line break
        $pos = strrpos(substr($text, $start, $end - $start), "\n");
        if ($pos !== false) return $start + $pos + 1;

        return $end;
    }
}
