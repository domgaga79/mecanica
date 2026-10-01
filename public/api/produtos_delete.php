<?php
/**
 * API: excluir produto (soft delete)
 *
 * Mantém o histórico dos orçamentos porque não apaga fisicamente o produto.
 * Apenas marca deleted_at para removê-lo das listagens futuras.
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

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'erro' => 'Método inválido'
        ]);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode([
            'ok' => false,
            'erro' => 'ID inválido'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    // 🔒 Lock do produto para impedir exclusões concorrentes inconsistentes.
    $check = $pdo->prepare(" 
        SELECT id, deleted_at
        FROM produtos
        WHERE id = ?
        AND empresa_id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $check->execute([$id, $empresa_id]);
    $produto = $check->fetch(PDO::FETCH_ASSOC);

    if (!$produto) {
        throw new Exception('Produto não encontrado');
    }

    if (!empty($produto['deleted_at'])) {
        throw new Exception('Produto já foi excluído');
    }

    // 🔥 Soft delete: preserva histórico dos orçamentos já emitidos.
    $stmt = $pdo->prepare(" 
        UPDATE produtos
        SET deleted_at = NOW()
        WHERE id = ?
        AND empresa_id = ?
        AND deleted_at IS NULL
    ");
    $stmt->execute([$id, $empresa_id]);

    if ($stmt->rowCount() < 1) {
        throw new Exception('Não foi possível excluir o produto');
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'mensagem' => 'Produto excluído com sucesso'
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage()
    ]);
}
