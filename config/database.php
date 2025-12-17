<?php
/**
 * Database Configuration
 * نظام إدارة محل أجهزة منزلية
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'store_management');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    die("خطأ في الاتصال بقاعدة البيانات: " . $e->getMessage());
}

/**
 * Execute a query and return results
 */
function query($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get single row
 */
function getRow($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Get all rows
 */
function getRows($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Insert and return last insert ID
 */
function insert($sql, $params = []) {
    global $pdo;
    $stmt = query($sql, $params);
    return $stmt ? $pdo->lastInsertId() : false;
}

/**
 * Update/Delete and return affected rows
 */
function execute($sql, $params = []) {
    $stmt = query($sql, $params);
    return $stmt ? $stmt->rowCount() : false;
}

/**
 * Begin transaction
 */
function beginTransaction() {
    global $pdo;
    return $pdo->beginTransaction();
}

/**
 * Commit transaction
 */
function commit() {
    global $pdo;
    return $pdo->commit();
}

/**
 * Rollback transaction
 */
function rollback() {
    global $pdo;
    return $pdo->rollBack();
}
