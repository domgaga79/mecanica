<?php
/**
 * API de clientes
 *
 * Lista clientes da empresa logada, com busca opcional por nome ou WhatsApp.
 */

declare(strict_types=1);

date_default_timezone_set('America/Bahia');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

if (function_exists('checkAuthApi')) {
    checkAuthApi();
} else {
    checkAuth();
}

$empresa_id = (int) empresa_id();

if ($empresa_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Empresa não identificada'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'erro' => 'Método inválido'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $busca = trim((string)($_GET['busca'] ?? $_GET['q'] ?? ''));
    $limite = (int)($_GET['limite'] ?? 50);

    if ($limite <= 0 || $limite > 100) {
        $limite = 50;
    }

    $params = [$empresa_id];

    $where = "
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        AND status = 'ativo'
    ";

    if ($busca !== '') {
        $buscaNumerica = preg_replace('/\D+/', '', $busca);

        $where .= "
            AND (
                nome LIKE ?
                OR whatsapp LIKE ?
            )
        ";

        $params[] = '%' . $busca . '%';
        $params[] = '%' . ($buscaNumerica !== '' ? $buscaNumerica : $busca) . '%';
    }

    $sql = "
        SELECT 
            id,
            nome,
            whatsapp,
            status,
            created_at,
            updated_at
        FROM clientes
        {$where}
        ORDER BY nome ASC, id DESC
        LIMIT {$limite}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'ok' => true,
        'clientes' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => 'Erro ao buscar clientes'
    ], JSON_UNESCAPED_UNICODE);
}
