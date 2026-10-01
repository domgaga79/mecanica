<?php
/**
 * API pública oficial para atualizar status de orçamento por token.
 *
 * Aceita POST form-urlencoded ou JSON:
 * - token: token público do orçamento
 * - acao: aprovar|aprovado|recusar|recusado|visualizar|visualizado
 *
 * Não depende de sessão.
 * Não aceita ID vindo do cliente.
 */

ob_start();

header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);
error_reporting(0);
date_default_timezone_set('America/Bahia');

require_once __DIR__ . '/../../config/db.php';

function resposta_publica($ok, array $dados = [], int $httpCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($httpCode);
    echo json_encode(array_merge(['ok' => $ok], $dados), JSON_UNESCAPED_UNICODE);
    exit;
}

function input_publico(): array
{
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    if (stripos($contentType, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);
        return is_array($json) ? $json : [];
    }

    return $_POST;
}

function token_publico($token): string
{
    $token = trim((string)$token);

    if ($token === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $token)) {
        return '';
    }

    return $token;
}

function acao_para_status_publico(string $acao): ?string
{
    $acao = strtolower(trim($acao));

    $mapa = [
        'aprovar'     => 'aprovado',
        'aprovado'    => 'aprovado',
        'recusar'     => 'recusado',
        'recusado'    => 'recusado',
        'visualizar'  => 'visualizado',
        'visualizado' => 'visualizado',
    ];

    return $mapa[$acao] ?? null;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta_publica(false, ['erro' => 'Método inválido'], 405);
}

$input = input_publico();

$token = token_publico($input['token'] ?? '');
$acao  = trim((string)($input['acao'] ?? $input['status'] ?? ''));

if ($token === '' || $acao === '') {
    resposta_publica(false, ['erro' => 'Dados inválidos'], 400);
}

$novoStatus = acao_para_status_publico($acao);

if (!$novoStatus) {
    resposta_publica(false, ['erro' => 'Ação inválida'], 400);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("\n        SELECT\n            o.*,\n            e.status AS empresa_status,\n            e.plano_id,\n            COALESCE(p.limite_produtos, 0) AS limite_produtos\n        FROM orcamentos o\n        JOIN empresas e ON e.id = o.empresa_id\n        LEFT JOIN planos p ON p.id = e.plano_id\n        WHERE o.token = ?\n        AND o.deleted_at IS NULL\n        LIMIT 1\n        FOR UPDATE\n    ");
    $stmt->execute([$token]);
    $orc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orc) {
        throw new Exception('Orçamento não encontrado');
    }

    if (($orc['empresa_status'] ?? '') !== 'ativa') {
        throw new Exception('Esta proposta está temporariamente indisponível');
    }

    $empresa_id   = (int)$orc['empresa_id'];
    $orcamento_id = (int)$orc['id'];
    $statusAtual  = (string)$orc['status'];

    if (in_array($statusAtual, ['aprovado', 'recusado'], true)) {
        $pdo->commit();
        resposta_publica(true, [
            'id' => $orcamento_id,
            'status' => $statusAtual,
            'mensagem' => 'Orçamento já finalizado'
        ]);
    }

    if ($novoStatus === 'visualizado') {
        if ($statusAtual !== 'enviado') {
            $pdo->commit();
            resposta_publica(true, [
                'id' => $orcamento_id,
                'status' => $statusAtual
            ]);
        }

        $stmt = $pdo->prepare("\n            UPDATE orcamentos\n            SET status = 'visualizado'\n            WHERE id = ?\n            AND empresa_id = ?\n            AND status = 'enviado'\n            AND deleted_at IS NULL\n        ");
        $stmt->execute([$orcamento_id, $empresa_id]);

        $pdo->commit();
        resposta_publica(true, [
            'id' => $orcamento_id,
            'status' => 'visualizado'
        ]);
    }

    if (!in_array($statusAtual, ['rascunho', 'enviado', 'visualizado'], true)) {
        throw new Exception('Status atual não permite esta ação');
    }

    $aprovadoEm = $novoStatus === 'aprovado' ? date('Y-m-d H:i:s') : null;

    $stmt = $pdo->prepare("\n        UPDATE orcamentos\n        SET status = ?,\n            aprovado_em = CASE WHEN ? IS NOT NULL THEN ? ELSE aprovado_em END,\n            aprovado_por = CASE WHEN ? IS NOT NULL THEN NULL ELSE aprovado_por END\n        WHERE id = ?\n        AND empresa_id = ?\n        AND status NOT IN ('aprovado','recusado')\n        AND deleted_at IS NULL\n    ");
    $stmt->execute([
        $novoStatus,
        $aprovadoEm,
        $aprovadoEm,
        $aprovadoEm,
        $orcamento_id,
        $empresa_id
    ]);

    if ($stmt->rowCount() === 0) {
        $pdo->commit();
        resposta_publica(true, [
            'id' => $orcamento_id,
            'status' => $statusAtual
        ]);
    }

    if ($novoStatus === 'aprovado') {
        // Associa/cria cliente, sem depender de sessão.
        $clienteId = !empty($orc['cliente_id']) ? (int)$orc['cliente_id'] : null;

        if (!$clienteId && trim((string)($orc['cliente_nome'] ?? '')) !== '') {
            $stmt = $pdo->prepare("\n                SELECT id\n                FROM clientes\n                WHERE empresa_id = ?\n                AND nome = ?\n                AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $stmt->execute([$empresa_id, $orc['cliente_nome']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cliente) {
                $clienteId = (int)$cliente['id'];
            } else {
                $stmt = $pdo->prepare("\n                    INSERT INTO clientes (empresa_id, nome, whatsapp, status)\n                    VALUES (?, ?, ?, 'ativo')\n                ");
                $stmt->execute([
                    $empresa_id,
                    $orc['cliente_nome'],
                    preg_replace('/\D+/', '', (string)($orc['cliente_whatsapp'] ?? ''))
                ]);
                $clienteId = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("\n                UPDATE orcamentos\n                SET cliente_id = ?\n                WHERE id = ?\n                AND empresa_id = ?\n                AND deleted_at IS NULL\n            ");
            $stmt->execute([$clienteId, $orcamento_id, $empresa_id]);
        }

        // Cadastra produtos inexistentes, respeitando o limite do plano.
        $limiteProdutos = (int)($orc['limite_produtos'] ?? 0);

        $stmt = $pdo->prepare("\n            SELECT COUNT(*)\n            FROM produtos\n            WHERE empresa_id = ?\n            AND deleted_at IS NULL\n        ");
        $stmt->execute([$empresa_id]);
        $produtosAtivos = (int)$stmt->fetchColumn();

        $stmt = $pdo->prepare("\n            SELECT nome_snapshot, preco_snapshot\n            FROM orcamento_itens\n            WHERE orcamento_id = ?\n            AND empresa_id = ?\n            ORDER BY id ASC\n        ");
        $stmt->execute([$orcamento_id, $empresa_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as $item) {
            $nomeProduto = trim((string)($item['nome_snapshot'] ?? ''));

            if ($nomeProduto === '') {
                continue;
            }

            $stmt = $pdo->prepare("\n                SELECT id\n                FROM produtos\n                WHERE empresa_id = ?\n                AND nome = ?\n                AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $stmt->execute([$empresa_id, $nomeProduto]);
            $produto = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($produto) {
                continue;
            }

            if ($limiteProdutos > 0 && $produtosAtivos >= $limiteProdutos) {
                continue;
            }

            $stmt = $pdo->prepare("\n                INSERT INTO produtos (empresa_id, nome, preco)\n                VALUES (?, ?, ?)\n            ");
            $stmt->execute([
                $empresa_id,
                $nomeProduto,
                max(0, (float)($item['preco_snapshot'] ?? 0))
            ]);

            $produtosAtivos++;
        }
    }

    $pdo->commit();

    resposta_publica(true, [
        'id' => $orcamento_id,
        'status' => $novoStatus,
        'mensagem' => $novoStatus === 'aprovado' ? 'Orçamento aprovado com sucesso' : 'Orçamento recusado com sucesso'
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    resposta_publica(false, ['erro' => $e->getMessage()], 500);
}
