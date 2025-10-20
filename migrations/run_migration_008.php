<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Utils\Database;

echo "Running migration: 008_add_workplace_edit_request_fields.sql\n";

try {
    $pdo = Database::getInstance();
    $sql = file_get_contents(__DIR__ . '/008_add_workplace_edit_request_fields.sql');

    if ($sql === false) {
        throw new Exception("Could not read migration file.");
    }

    $pdo->exec($sql);
    echo "Migration 008_add_workplace_edit_request_fields.sql applied successfully.\n";
} catch (Exception $e) {
    echo "Error applying migration: " . $e->getMessage() . "\n";
    exit(1);
}
?>
