<?php
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json; charset=utf-8');

checkAuthApi();

$response = ['ok' => false];

try {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    $empresa_id = empresa_id();

    if (!$empresa_id) {
        throw new Exception('Empresa não identificada');
    }

    $id    = isset($_POST['id']) && $_POST['id'] !== '' ? (int)$_POST['id'] : null;
    $nome  = trim($_POST['nome'] ?? '');
    $preco = (float)($_POST['preco'] ?? 0);

    if ($nome === '') {
        throw new Exception('Nome é obrigatório');
    }

    if ($preco < 0) {
        throw new Exception('Preço inválido');
    }

    $pdo->beginTransaction();

    // 🔒 LOCK DA EMPRESA: evita dois cadastros simultâneos ultrapassarem o limite.
    $stmt = $pdo->prepare("SELECT id FROM empresas WHERE id = ? FOR UPDATE");
    $stmt->execute([$empresa_id]);

    if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
        throw new Exception('Empresa inválida');
    }

    if ($id) {

        // 🔒 Atualização não consome novo limite; apenas valida posse do produto.
        $check = $pdo->prepare(" 
            SELECT id 
            FROM produtos 
            WHERE id = ? 
              AND empresa_id = ? 
              AND deleted_at IS NULL
            LIMIT 1
        ");
        $check->execute([$id, $empresa_id]);

        if (!$check->fetch(PDO::FETCH_ASSOC)) {
            throw new Exception('Produto não encontrado');
        }

        $stmt = $pdo->prepare(" 
            UPDATE produtos
            SET nome = ?, preco = ?
            WHERE id = ?
              AND empresa_id = ?
              AND deleted_at IS NULL
        ");

        $stmt->execute([
            $nome,
            $preco,
            $id,
            $empresa_id
        ]);

    } else {

        // ================= LIMITE DE PRODUTOS DO PLANO =================
        $stmt = $pdo->prepare(" 
            SELECT 
                p.nome AS plano_nome,
                p.limite_produtos
            FROM empresas e
            JOIN planos p ON p.id = e.plano_id
            WHERE e.id = ?
            LIMIT 1
        ");
        $stmt->execute([$empresa_id]);
        $plano = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$plano) {
            throw new Exception('Plano da empresa não encontrado');
        }

        $limiteProdutos = (int)($plano['limite_produtos'] ?? 0);
        $nomePlano      = $plano['plano_nome'] ?? 'atual';

        if ($limiteProdutos <= 0) {
            throw new Exception('O plano atual não permite cadastro de produtos');
        }

        $stmt = $pdo->prepare(" 
            SELECT COUNT(*)
            FROM produtos
            WHERE empresa_id = ?
              AND deleted_at IS NULL
        ");
        $stmt->execute([$empresa_id]);
        $produtosAtivos = (int)$stmt->fetchColumn();

        if ($produtosAtivos >= $limiteProdutos) {
            throw new Exception("Limite de produtos do plano {$nomePlano} atingido ({$limiteProdutos})");
        }

        $stmt = $pdo->prepare(" 
            INSERT INTO produtos
            (empresa_id, nome, preco)
            VALUES (?, ?, ?)
        ");

        $stmt->execute([
            $empresa_id,
            $nome,
            $preco
        ]);
    }

    $pdo->commit();

    $response = ['ok' => true];

} catch (Throwable $e) {

    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('produtos_salvar.php: ' . $e->getMessage());

    $response = [
        'ok' => false,
        'erro' => $e->getMessage()
    ];
}

ob_end_clean();
echo json_encode($response, JSON_UNESCAPED_UNICODE);
