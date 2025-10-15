<?php

/**
 * Database Migration Runner
 * OJT Route - Run database migrations
 */

// require_once '../vendor/autoload.php'; // Not needed for this script

// Load environment variables
if (file_exists('../.env')) {
    $lines = file('../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

// Database configuration
$host = $_ENV['DB_HOST'] ?? 'localhost';
$port = $_ENV['DB_PORT'] ?? 3306;
$database = $_ENV['DB_DATABASE'] ?? 'ojtroute_db';
$username = $_ENV['DB_USERNAME'] ?? 'root';
$password = $_ENV['DB_PASSWORD'] ?? '';

try {
    // Connect to MySQL (without database first)
    $pdo = new PDO("mysql:host=$host;port=$port;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "✅ Connected to MySQL server\n";
    
    // Read and execute schema migration
    $schemaSql = file_get_contents(__DIR__ . '/001_create_database.sql');
    $statements = explode(';', $schemaSql);
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✅ Database schema created successfully\n";
    
            // Read and execute seed data migration
            $seedSql = file_get_contents(__DIR__ . '/002_seed_data.sql');
            $statements = explode(';', $seedSql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            echo "✅ Seed data inserted successfully\n";
            
            // Read and execute system configuration migration
            $configSql = file_get_contents(__DIR__ . '/003_system_config.sql');
            $statements = explode(';', $configSql);
            
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
            
            echo "✅ System configuration created successfully\n";
    
    // Verify tables were created
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "✅ Database tables created:\n";
    foreach ($tables as $table) {
        echo "   - $table\n";
    }
    
    // Verify admin account
    $stmt = $pdo->query("SELECT school_id, email, full_name, role FROM users WHERE role = 'admin'");
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($admin) {
        echo "✅ Default admin account created:\n";
        echo "   School ID: {$admin['school_id']}\n";
        echo "   Email: {$admin['email']}\n";
        echo "   Name: {$admin['full_name']}\n";
        echo "   Password: Admin@2024\n";
    }
    
    echo "\n🎉 Database migration completed successfully!\n";
    echo "You can now start the application at: http://localhost/ojtroute/public/\n";
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    exit(1);
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
