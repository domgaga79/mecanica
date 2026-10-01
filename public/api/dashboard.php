<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';

header('Content-Type: application/json; charset=utf-8');

checkAuthApi();

$empresa_id = empresa_id();

if (!$empresa_id) {
    http_response_code(401);
    echo json_encode([
        'ok' => false,
        'erro' => 'Empresa não identificada'
    ]);
    exit;
}

try {

    // ================= KPIs =================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_orcamentos,
            COALESCE(SUM(status = 'rascunho'), 0) AS rascunhos,
            COALESCE(SUM(status = 'enviado'), 0) AS enviados,
            COALESCE(SUM(status = 'visualizado'), 0) AS visualizados,
            COALESCE(SUM(status = 'aprovado'), 0) AS aprovados,
            COALESCE(SUM(status = 'recusado'), 0) AS recusados,
            COALESCE(SUM(valor_total), 0) AS total_proposto,
            COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END), 0) AS faturamento_aprovado
        FROM orcamentos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
    ");
    $stmt->execute([$empresa_id]);
    $kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $totalOrcamentos = (int)($kpi['total_orcamentos'] ?? 0);
    $aprovados = (int)($kpi['aprovados'] ?? 0);
    $recusados = (int)($kpi['recusados'] ?? 0);
    $concluidos = $aprovados + $recusados;

    $taxaFinal = $concluidos > 0 ? round(($aprovados / $concluidos) * 100, 1) : 0;
    $taxaGeral = $totalOrcamentos > 0 ? round(($aprovados / $totalOrcamentos) * 100, 1) : 0;

    // ================= MÊS ATUAL =================
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS orcamentos_mes,
            COALESCE(SUM(valor_total), 0) AS proposto_mes,
            COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END), 0) AS aprovado_mes
        FROM orcamentos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
        AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
    ");
    $stmt->execute([$empresa_id]);
    $mes = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // ================= GRÁFICO ÚLTIMOS 7 DIAS =================
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) AS dia,
            COALESCE(SUM(valor_total), 0) AS total
        FROM orcamentos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        AND status = 'aprovado'
        AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        GROUP BY DATE(created_at)
        ORDER BY dia ASC
    ");
    $stmt->execute([$empresa_id]);
    $grafico = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'total_proposto' => (float)($kpi['total_proposto'] ?? 0),
        'faturamento_aprovado' => (float)($kpi['faturamento_aprovado'] ?? 0),
        'total_orcamentos' => $totalOrcamentos,
        'rascunhos' => (int)($kpi['rascunhos'] ?? 0),
        'enviados' => (int)($kpi['enviados'] ?? 0),
        'visualizados' => (int)($kpi['visualizados'] ?? 0),
        'aprovados' => $aprovados,
        'recusados' => $recusados,
        'taxa_final' => $taxaFinal,
        'taxa_geral' => $taxaGeral,
        'mes' => [
            'orcamentos' => (int)($mes['orcamentos_mes'] ?? 0),
            'proposto' => (float)($mes['proposto_mes'] ?? 0),
            'aprovado' => (float)($mes['aprovado_mes'] ?? 0),
        ],
        'grafico_aprovado_7_dias' => $grafico
    ], JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'erro' => 'Erro ao carregar dashboard'
    ], JSON_UNESCAPED_UNICODE);
}
