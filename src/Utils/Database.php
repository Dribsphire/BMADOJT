<?php

namespace App\Utils;

use PDO;
use PDOException;

/**
 * Database Connection Utility
 * OJT Route - Database connection management
 */
class Database
{
    private static ?PDO $instance = null;
    
    /**
     * Get database connection instance
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        
        return self::$instance;
    }
    
    /**
     * Create new database connection
     */
    private static function createConnection(): PDO
    {
        // Load environment variables
        self::loadEnv();
        
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $port = $_ENV['DB_PORT'] ?? 3306;

        //$database = $_ENV['DB_DATABASE'] ?? 'ojtroute_db';
        //$username = $_ENV['DB_USERNAME'] ?? 'root';
        //$password = $_ENV['DB_PASSWORD'] ?? '';

        //coughtech hostinger database
        //$database = $_ENV['DB_DATABASE'] ?? 'u825404776_ojtroute_db';
        //$username = $_ENV['DB_USERNAME'] ?? 'u825404776_ojtroute_db';
        //$password = $_ENV['DB_PASSWORD'] ?? 'Hq6cge88123!';

        //school hostinger database
        $database = $_ENV['DB_DATABASE'] ?? 'u719275046_ojt_route';
        $username = $_ENV['DB_USERNAME'] ?? 'u719275046_ojt_route';
        $password = $_ENV['DB_PASSWORD'] ?? 'rmpL!3LfXgf@Uew';
        
        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        
        try {
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            throw new \Exception("Database connection failed: " . $e->getMessage());
        }
    }
    
    /**
     * Load environment variables from .env file
     */
    private static function loadEnv(): void
    {
        if (file_exists(__DIR__ . '/../../.env')) {
            $lines = file(__DIR__ . '/../../.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
                    list($key, $value) = explode('=', $line, 2);
                    $_ENV[trim($key)] = trim($value);
                }
            }
        }
    }
}
