<?php
/** Apply a .sql migration file (comment-aware splitter, idempotent-tolerant) */
function applySql($db, $file, $verbose = false) {
    $sql = file_get_contents($file);
    // strip line comments FIRST so statement chunks start at real SQL
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    $count = 0;
    foreach (array_filter(array_map('trim', preg_split('/;\s*[\r\n]+/', $sql))) as $q) {
        if ($q === '') continue;
        try { $db->exec($q); $count++; }
        catch (Exception $e) { if ($verbose) echo 'skip: ' . substr($e->getMessage(), 0, 70) . PHP_EOL; }
    }
    return $count;
}
