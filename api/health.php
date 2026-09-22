<?php
/**
 * Main Pintar - api/health.php
 * Health check endpoint for monitoring & PWA
 */
require_once __DIR__ . '/../includes/config.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $pdo->query('SELECT 1');
    $dbStatus = 'ok';
} catch (Exception $e) {
    $dbStatus = 'error: ' . $e->getMessage();
}

$status = ($dbStatus === 'ok') ? 'healthy' : 'unhealthy';
http_response_code($status === 'healthy' ? 200 : 503);

echo json_encode([
    'status' => $status,
    'timestamp' => date('c'),
    'version' => '1.0.0',
    'database' => $dbStatus,
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
], JSON_UNESCAPED_UNICODE);