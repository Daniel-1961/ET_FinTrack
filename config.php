<?php
/**
 * FinTrack ET - Safe Database Configuration
 * Employs PDO with strict error checking to prevent SQL injection and ensure robust connections.
 */

// Database Host Credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'fintrack_et');

try {
    // Connect via PDO with UTF-8 support
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Secure fail message preventing leaking sensitive credentials to standard users
    die("Database Connection failed securely. Please check that MySQL is running on your server. Detail: " . htmlspecialchars($e->getMessage()));
}
?>
