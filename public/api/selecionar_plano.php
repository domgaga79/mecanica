<?php
/**
 * API: selecionar_plano.php
 *
 * Troca de plano pelo próprio cliente, com proteção comercial:
 * - Planos pagos não podem ser ativados diretamente por esta API.
 * - O usuário só pode trocar para plano gratuito/sem preço.
 * - A troca é bloqueada se o uso atual exceder os limites do plano escolhido.
 */

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

checkAuthApi();

$empresa_id = empresa_id();
$usuario_id = usuario_id();

if (!$empresa_id || !$usuario_id) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Sessão inválida. Faça login novamente.'
    ]);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'ok' => false,
            'erro' => 'Método inválido.'
        ]);
        exit;
    }

    $plano_id = filter_input(INPUT_POST, 'plano_id', FILTER_VALIDATE_INT);

    if (!$plano_id || $plano_id <= 0) {
        throw new Exception('Plano não informado ou inválido.');
    }

    $pdo->beginTransaction();

    // Bloqueia a empresa durante a validação e atualização.
    $stmt = $pdo->prepare("\n        SELECT \n            e.id,\n            e.nome,\n            e.status,\n            e.plano_id AS plano_atual_id,\n            COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos,\n            p.nome AS plano_atual_nome\n        FROM empresas e\n        LEFT JOIN planos p ON p.id = e.plano_id\n        WHERE e.id = ?\n        FOR UPDATE\n    ");
    $stmt->execute([$empresa_id]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('Empresa não encontrada.');
    }

    if (($empresa['status'] ?? '') !== 'ativa') {
        http_response_code(403);
        throw new Exception('Empresa suspensa. Entre em contato com o suporte.');
    }

    $stmt = $pdo->prepare("\n        SELECT \n            id,\n            nome,\n            limite_orcamentos,\n            limite_produtos,\n            preco,\n            ativo\n        FROM planos\n        WHERE id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$plano_id]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plano || (int)$plano['ativo'] !== 1) {
        throw new Exception('Plano inválido ou inativo.');
    }

    if ((int)$empresa['plano_atual_id'] === (int)$plano['id']) {
        $pdo->commit();
        echo json_encode([
            'ok' => true,
            'mensagem' => 'Este já é o plano atual.',
            'plano' => $plano['nome']
        ]);
        exit;
    }

    // Segurança comercial: planos pagos não podem ser ativados diretamente.
    // Upgrade para PRO continua via WhatsApp/atendimento ou painel administrativo da Bacuri.
    if ((float)$plano['preco'] > 0) {
        http_response_code(403);
        throw new Exception('Planos pagos precisam ser ativados pela Bacuri Digital. Use o botão de contato para solicitar o upgrade.');
    }

    $limiteExtra = max(0, (int)($empresa['limite_extra_orcamentos'] ?? 0));
    $limiteOrcamentosTotal = (int)$plano['limite_orcamentos'] + $limiteExtra;
    $limiteProdutos = (int)$plano['limite_produtos'];

    // Uso de orçamentos no mês atual.
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM orcamentos\n        WHERE empresa_id = ?\n          AND deleted_at IS NULL\n          AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')\n    ");
    $stmt->execute([$empresa_id]);
    $orcamentosMes = (int)$stmt->fetchColumn();

    // Produtos ativos.
    $stmt = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM produtos\n        WHERE empresa_id = ?\n          AND deleted_at IS NULL\n    ");
    $stmt->execute([$empresa_id]);
    $produtosAtivos = (int)$stmt->fetchColumn();

    if ($limiteOrcamentosTotal > 0 && $orcamentosMes > $limiteOrcamentosTotal) {
        throw new Exception("Não é possível mudar para {$plano['nome']}: a empresa já possui {$orcamentosMes} orçamento(s) no mês e o limite desse plano com extra é {$limiteOrcamentosTotal}.");
    }

    if ($limiteProdutos > 0 && $produtosAtivos > $limiteProdutos) {
        throw new Exception("Não é possível mudar para {$plano['nome']}: a empresa possui {$produtosAtivos} produto(s) ativo(s) e o limite desse plano é {$limiteProdutos}.");
    }

    $stmt = $pdo->prepare("\n        UPDATE empresas\n        SET plano_id = ?,\n            assinatura = ?\n        WHERE id = ?\n        LIMIT 1\n    ");
    $stmt->execute([
        (int)$plano['id'],
        $plano['nome'],
        $empresa_id
    ]);

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'mensagem' => 'Plano atualizado com sucesso.',
        'plano' => $plano['nome'],
        'uso' => [
            'orcamentos_mes' => $orcamentosMes,
            'limite_orcamentos_plano' => (int)$plano['limite_orcamentos'],
            'limite_extra_orcamentos' => $limiteExtra,
            'limite_orcamentos_total' => $limiteOrcamentosTotal,
            'produtos_ativos' => $produtosAtivos,
            'limite_produtos' => $limiteProdutos
        ]
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (http_response_code() === 200) {
        http_response_code(400);
    }

    error_log('Erro selecionar_plano.php: ' . $e->getMessage());

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage()
    ]);
}
