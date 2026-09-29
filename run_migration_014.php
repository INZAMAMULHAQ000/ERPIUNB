<?php
require_once 'config/database.php';
$db = getDB();
$sql = file_get_contents('config/migrations/014_fixed_assets_f9.sql');
$sql = preg_replace('/^USE\s+\w+;/mi', '', $sql);
$sql = preg_replace('/^--.*$/m', '', $sql);
$sql = preg_replace('/\/\*.*?\*\//s', '', $sql);
$statements = array_filter(
    array_map('trim', explode(';', $sql)),
    function($s) { return !empty($s); }
);
$count = 0;
$errors = [];
foreach ($statements as $stmt) {
    try {
        $db->exec($stmt);
        $count++;
    } catch (PDOException $e) {
        $errors[] = $e->getMessage();
    }
}
echo "Executed: $count statements\n";
if ($errors) {
    echo "Errors: " . implode('; ', $errors) . "\n";
} else {
    echo "Success!\n";
}