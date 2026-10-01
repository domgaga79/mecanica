<?php
/**
 * API: Detalhe de orçamento
 *
 * Modos suportados:
 * - Interno autenticado: /api/orcamento_detalhe.php?id=123
 * - Público por token:   /api/orcamento_detalhe.php?t=TOKEN
 * - Público legado:      /api/orcamento_detalhe.php?token=TOKEN
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function responder_json(array $payload, int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function base_url_atual(): string
{
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^a-zA-Z0-9\.\-\:\_]/', '', $host);

    return $scheme . '://' . $host;
}

function dinheiro_float($valor): float
{
    return round((float)($valor ?? 0), 2);
}

try {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $token = trim((string)($_GET['t'] ?? $_GET['token'] ?? ''));

    $modoPublico = $token !== '';
    $empresaLogadaId = 0;

    if (!$modoPublico) {
        checkAuthApi();
        $empresaLogadaId = (int)empresa_id();

        if ($empresaLogadaId <= 0) {
            responder_json([
                'ok' => false,
                'erro' => 'Empresa não identificada'
            ], 403);
        }
    }

    if (!$modoPublico && $id <= 0) {
        responder_json([
            'ok' => false,
            'erro' => 'Informe o ID do orçamento'
        ], 400);
    }

    if ($modoPublico && !preg_match('/^[a-f0-9]{32,64}$/i', $token)) {
        responder_json([
            'ok' => false,
            'erro' => 'Token inválido'
        ], 400);
    }

    // ================= BUSCA ORÇAMENTO =================
    if ($modoPublico) {
        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.numero,
                o.empresa_id,
                o.cliente_id,
                o.cliente_nome,
                o.cliente_whatsapp,
                o.valor_total,
                o.status,
                o.created_at,
                o.observacoes,
                o.token,
                o.validade,
                o.aprovado_em,
                o.criado_por,
                o.aprovado_por,
                e.nome AS empresa_nome,
                e.slug AS empresa_slug,
                e.logo AS empresa_logo,
                e.whatsapp AS empresa_whatsapp,
                e.status AS empresa_status
            FROM orcamentos o
            JOIN empresas e ON e.id = o.empresa_id
            WHERE o.token = ?
            AND o.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$token]);
    } else {
        $stmt = $pdo->prepare("
            SELECT
                o.id,
                o.numero,
                o.empresa_id,
                o.cliente_id,
                o.cliente_nome,
                o.cliente_whatsapp,
                o.valor_total,
                o.status,
                o.created_at,
                o.observacoes,
                o.token,
                o.validade,
                o.aprovado_em,
                o.criado_por,
                o.aprovado_por,
                e.nome AS empresa_nome,
                e.slug AS empresa_slug,
                e.logo AS empresa_logo,
                e.whatsapp AS empresa_whatsapp,
                e.status AS empresa_status
            FROM orcamentos o
            JOIN empresas e ON e.id = o.empresa_id
            WHERE o.id = ?
            AND o.empresa_id = ?
            AND o.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$id, $empresaLogadaId]);
    }

    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        responder_json([
            'ok' => false,
            'erro' => 'Orçamento não encontrado'
        ], 404);
    }

    if (($orcamento['empresa_status'] ?? '') !== 'ativa') {
        responder_json([
            'ok' => false,
            'erro' => 'Empresa suspensa. Entre em contato com o suporte.'
        ], 403);
    }

    $orcamentoId = (int)$orcamento['id'];
    $empresaOrcamentoId = (int)$orcamento['empresa_id'];

    // ================= AUTO STATUS PÚBLICO =================
    // Quando o cliente abre o orçamento por token pela primeira vez, muda de enviado para visualizado.
    if ($modoPublico && $orcamento['status'] === 'enviado') {
        $stmtUpdate = $pdo->prepare("\n            UPDATE orcamentos\n            SET status = 'visualizado'\n            WHERE id = ?\n            AND empresa_id = ?\n            AND status = 'enviado'\n            AND deleted_at IS NULL\n            LIMIT 1\n        ");
        $stmtUpdate->execute([$orcamentoId, $empresaOrcamentoId]);
        $orcamento['status'] = 'visualizado';
    }

    // ================= ITENS =================
    $stmt = $pdo->prepare("\n        SELECT\n            id,\n            nome_snapshot AS nome,\n            preco_snapshot AS preco,\n            quantidade,\n            total\n        FROM orcamento_itens\n        WHERE orcamento_id = ?\n        AND empresa_id = ?\n        ORDER BY id ASC\n    ");
    $stmt->execute([$orcamentoId, $empresaOrcamentoId]);
    $itensRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $itens = [];
    $totalRecalculado = 0.0;
    $quantidadeItens = 0;

    foreach ($itensRaw as $item) {
        $preco = dinheiro_float($item['preco']);
        $qtd = max(0, (int)($item['quantidade'] ?? 0));
        $totalItem = dinheiro_float($item['total']);

        // Blindagem: se o total salvo estiver vazio/inconsistente, calcula pela linha.
        if ($totalItem < 0 || ($totalItem == 0.0 && $preco > 0 && $qtd > 0)) {
            $totalItem = dinheiro_float($preco * $qtd);
        }

        $itens[] = [
            'id' => (int)$item['id'],
            'nome' => (string)($item['nome'] ?? ''),
            'preco' => $preco,
            'quantidade' => $qtd,
            'qtd' => $qtd, // compatibilidade com telas antigas
            'total' => $totalItem,
        ];

        $totalRecalculado += $totalItem;
        $quantidadeItens += $qtd;
    }

    $baseUrl = base_url_atual();
    $urlPublica = $baseUrl . '/publico.php?t=' . rawurlencode((string)$orcamento['token']);
    $urlPdfInterna = $baseUrl . '/pdf_orcamento.php?id=' . $orcamentoId;
    $urlPdfPublica = $baseUrl . '/pdf_orcamento.php?t=' . rawurlencode((string)$orcamento['token']);

    // Normaliza tipos para o frontend.
    $orcamento['id'] = $orcamentoId;
    $orcamento['numero'] = (int)($orcamento['numero'] ?? $orcamentoId);
    $orcamento['empresa_id'] = $empresaOrcamentoId;
    $orcamento['cliente_id'] = $orcamento['cliente_id'] !== null ? (int)$orcamento['cliente_id'] : null;
    $orcamento['valor_total'] = dinheiro_float($orcamento['valor_total']);
    $orcamento['total_recalculado'] = dinheiro_float($totalRecalculado);
    $orcamento['quantidade_itens'] = $quantidadeItens;
    $orcamento['url_publica'] = $urlPublica;
    $orcamento['url_pdf'] = $modoPublico ? $urlPdfPublica : $urlPdfInterna;
    $orcamento['url_pdf_publica'] = $urlPdfPublica;
    $orcamento['modo'] = $modoPublico ? 'publico' : 'interno';

    responder_json([
        'ok' => true,
        'modo' => $modoPublico ? 'publico' : 'interno',
        'orcamento' => $orcamento,
        'itens' => $itens,
    ]);

} catch (Throwable $e) {
    error_log('[orcamento_detalhe] ' . $e->getMessage());

    responder_json([
        'ok' => false,
        'erro' => 'Erro ao carregar detalhes do orçamento'
    ], 500);
}
