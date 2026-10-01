<?php
require_once __DIR__ . '/env.php';

// Produção: não exibe detalhes técnicos para o usuário.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

try {
    $host = app_config_required('DB_HOST');
    $db   = app_config_required('DB_NAME');
    $user = app_config_required('DB_USER');
    $pass = app_config_required('DB_PASS');

    $pdo = new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (Throwable $e) {
    error_log('[DB] ' . $e->getMessage());

    http_response_code(500);
    exit('Erro interno ao conectar ao banco de dados.');
}
