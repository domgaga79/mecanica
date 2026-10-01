<?php
/**
 * Autocomplete seguro de serviços/produtos
 *
 * Busca sugestões no catálogo ativo da empresa logada e no histórico de itens
 * dos orçamentos da própria empresa.
 *
 * Mantém retorno em array simples para compatibilidade com chamadas antigas:
 * [ {"nome":"...", "preco":"..."}, ... ]
 */

declare(strict_types=1);

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
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $busca = trim((string)($_GET['q'] ?? $_GET['busca'] ?? ''));

    if (mb_strlen($busca, 'UTF-8') < 2) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $like = '%' . $busca . '%';
    $resultados = [];

    // 1) Catálogo ativo da empresa logada.
    $stmt = $pdo->prepare(" 
        SELECT 
            nome,
            preco
        FROM produtos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        AND nome LIKE ?
        ORDER BY nome ASC
        LIMIT 20
    ");
    $stmt->execute([$empresa_id, $like]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $produto) {
        $nome = trim((string)($produto['nome'] ?? ''));

        if ($nome === '') {
            continue;
        }

        $chave = mb_strtolower($nome, 'UTF-8');

        $resultados[$chave] = [
            'nome' => $nome,
            'preco' => number_format((float)$produto['preco'], 2, '.', '')
        ];
    }

    // 2) Histórico da própria empresa, sem cruzar dados de outras empresas.
    $stmt = $pdo->prepare(" 
        SELECT 
            oi.nome_snapshot AS nome,
            oi.preco_snapshot AS preco,
            MAX(o.created_at) AS ultima_ocorrencia
        FROM orcamento_itens oi
        INNER JOIN orcamentos o ON o.id = oi.orcamento_id
        WHERE oi.empresa_id = ?
        AND o.empresa_id = ?
        AND o.deleted_at IS NULL
        AND oi.nome_snapshot LIKE ?
        GROUP BY oi.nome_snapshot, oi.preco_snapshot
        ORDER BY ultima_ocorrencia DESC
        LIMIT 20
    ");
    $stmt->execute([$empresa_id, $empresa_id, $like]);

    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $nome = trim((string)($item['nome'] ?? ''));

        if ($nome === '') {
            continue;
        }

        $chave = mb_strtolower($nome, 'UTF-8');

        // Prioriza o preço atual do catálogo quando o nome já veio de produtos.
        if (!isset($resultados[$chave])) {
            $resultados[$chave] = [
                'nome' => $nome,
                'preco' => number_format((float)$item['preco'], 2, '.', '')
            ];
        }
    }

    echo json_encode(array_slice(array_values($resultados), 0, 20), JSON_UNESCAPED_UNICODE);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
