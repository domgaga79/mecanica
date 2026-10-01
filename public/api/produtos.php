<?php
/**
 * API: listar/buscar produtos da empresa logada.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json; charset=utf-8');

checkAuthApi();

$empresa_id = (int)empresa_id();

if ($empresa_id <= 0) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Empresa não identificada'
    ]);
    exit;
}

$q  = trim($_GET['q'] ?? '');
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // ================= BUSCAR POR ID =================
    if ($id > 0) {
        $stmt = $pdo->prepare(" 
            SELECT id, nome, preco
            FROM produtos
            WHERE id = ?
            AND empresa_id = ?
            AND deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id, $empresa_id]);

        echo json_encode([
            'ok' => true,
            'produto' => $stmt->fetch(PDO::FETCH_ASSOC) ?: null
        ]);
        exit;
    }

    // ================= AUTOCOMPLETE / BUSCA =================
    if (mb_strlen($q, 'UTF-8') >= 2) {
        $like = '%' . $q . '%';

        $stmt = $pdo->prepare(" 
            SELECT id, nome, preco
            FROM produtos
            WHERE empresa_id = ?
            AND deleted_at IS NULL
            AND nome LIKE ?
            ORDER BY nome ASC
            LIMIT 20
        ");
        $stmt->execute([$empresa_id, $like]);

        echo json_encode([
            'ok' => true,
            'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ]);
        exit;
    }

    // ================= LISTA COMPLETA =================
    $stmt = $pdo->prepare(" 
        SELECT id, nome, preco
        FROM produtos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        ORDER BY id DESC
    ");
    $stmt->execute([$empresa_id]);

    echo json_encode([
        'ok' => true,
        'produtos' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);

} catch (Throwable $e) {
    error_log('[produtos.php] ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => 'Erro ao buscar produtos'
    ]);
}
