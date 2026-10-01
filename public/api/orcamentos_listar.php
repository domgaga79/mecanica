<?php
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');

header('Content-Type: application/json; charset=utf-8');

$root = dirname(__DIR__, 2);

require_once $root . '/config/db.php';
require_once $root . '/core/auth.php';
require_once $root . '/core/empresa.php';

function app_base_url(): string
{
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

try {
    checkAuth();

    if (!isset($pdo)) {
        throw new Exception('PDO não inicializado');
    }

    $empresa_id = empresa_id();

    if (!$empresa_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Empresa não identificada']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'erro' => 'Método inválido']);
        exit;
    }

    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = min(50, max(5, (int)($_GET['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;

    $q = trim((string)($_GET['q'] ?? ''));
    $status = trim((string)($_GET['status'] ?? ''));

    $statusPermitidos = ['rascunho', 'enviado', 'visualizado', 'aprovado', 'recusado'];

    if ($status !== '' && !in_array($status, $statusPermitidos, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Status inválido']);
        exit;
    }

    $where = 'WHERE o.empresa_id = ? AND o.deleted_at IS NULL';
    $params = [$empresa_id];

    if ($status !== '') {
        $where .= ' AND o.status = ?';
        $params[] = $status;
    }

    if ($q !== '') {
        if (ctype_digit($q)) {
            $where .= ' AND (o.id = ? OR o.numero = ? OR o.cliente_nome LIKE ? OR o.cliente_whatsapp LIKE ?)';
            $params[] = (int)$q;
            $params[] = (int)$q;
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        } else {
            $where .= ' AND (o.cliente_nome LIKE ? OR o.cliente_whatsapp LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . preg_replace('/\D/', '', $q) . '%';
        }
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM orcamentos o $where");
    $stmt->execute($params);
    $total = (int)$stmt->fetchColumn();

    $stmtResumo = $pdo->prepare("
        SELECT
            COUNT(*) AS total_filtrado,
            COALESCE(SUM(o.valor_total), 0) AS total_proposto,
            COALESCE(SUM(CASE WHEN o.status = 'aprovado' THEN o.valor_total ELSE 0 END), 0) AS total_aprovado,
            COALESCE(SUM(COALESCE(itens.total_itens, 0)), 0) AS quantidade_itens
        FROM orcamentos o
        LEFT JOIN (
            SELECT
                orcamento_id,
                empresa_id,
                COUNT(*) AS total_itens
            FROM orcamento_itens
            GROUP BY orcamento_id, empresa_id
        ) itens ON itens.orcamento_id = o.id
               AND itens.empresa_id = o.empresa_id
        $where
    ");

    $stmtResumo->execute($params);
    $resumo = $stmtResumo->fetch(PDO::FETCH_ASSOC) ?: [];

    $stmt = $pdo->prepare("
        SELECT
            o.id,
            o.numero,
            o.token,
            o.cliente_nome,
            o.cliente_whatsapp,
            o.valor_total,
            o.status,
            o.created_at,
            o.validade,
            o.aprovado_em,
            (
                SELECT COUNT(*)
                FROM orcamento_itens i
                WHERE i.orcamento_id = o.id
                AND i.empresa_id = o.empresa_id
            ) AS total_itens
        FROM orcamentos o
        $where
        ORDER BY o.id DESC
        LIMIT $limit OFFSET $offset
    ");

    $stmt->execute($params);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $baseUrl = app_base_url();

    foreach ($dados as &$orcamento) {
        $orcamento['url_publica'] = $baseUrl . '/publico.php?t=' . rawurlencode((string)$orcamento['token']);
        $orcamento['pdf_url'] = $baseUrl . '/pdf_orcamento.php?id=' . (int)$orcamento['id'];
        $orcamento['url_whatsapp'] = null;

        $telefone = preg_replace('/\D/', '', (string)($orcamento['cliente_whatsapp'] ?? ''));

        if ($telefone !== '') {
            $numeroExibicao = (int)($orcamento['numero'] ?? $orcamento['id']);
            $mensagem = "🧾 Orçamento #{$numeroExibicao}\n\n";
            $mensagem .= "👤 Cliente: {$orcamento['cliente_nome']}\n";
            $mensagem .= "💰 Valor: R$ " . number_format((float)$orcamento['valor_total'], 2, ',', '.') . "\n\n";
            $mensagem .= "🔗 Acesse seu orçamento:\n" . $orcamento['url_publica'] . "\n\n";
            $mensagem .= "✔ Você pode aprovar ou recusar diretamente no link.";

            $orcamento['url_whatsapp'] = 'https://api.whatsapp.com/send?phone=55' . $telefone . '&text=' . rawurlencode($mensagem);
        }
    }
    unset($orcamento);

    $pages = $total > 0 ? (int)ceil($total / $limit) : 1;

    echo json_encode([
        'ok' => true,
        'data' => $dados,
        'resumo' => [
            'total_filtrado' => (int)($resumo['total_filtrado'] ?? 0),
            'total_proposto' => (float)($resumo['total_proposto'] ?? 0),
            'total_aprovado' => (float)($resumo['total_aprovado'] ?? 0),
            'quantidade_itens' => (int)($resumo['quantidade_itens'] ?? 0),
        ],
        'paginacao' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => $pages,
        ],
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage(),
    ]);
}
