<?php

require_once 'vendor/autoload.php';
use App\Utils\Database;

try {
    $pdo = Database::getInstance();
    
    // Check users table
    $stmt = $pdo->prepare('SELECT id, school_id, full_name FROM users ORDER BY id');
    $stmt->execute();
    $users = $stmt->fetchAll();
    
    echo "Users in database:\n";
    foreach($users as $user) {
        echo "ID: " . $user['id'] . ", School ID: " . $user['school_id'] . ", Name: " . $user['full_name'] . "\n";
    }
    
    // Check activity_logs table
    $stmt = $pdo->prepare('SELECT COUNT(*) as count FROM activity_logs');
    $stmt->execute();
    $count = $stmt->fetchColumn();
    echo "\nActivity logs count: " . $count . "\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
