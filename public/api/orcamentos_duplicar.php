<?php
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

checkAuthApi();

function responderJson(array $dados, int $status = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = empresa_id();
$usuario_id = usuario_id();

if (!$empresa_id) {
    responderJson(['ok' => false, 'erro' => 'Empresa não identificada'], 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderJson(['ok' => false, 'erro' => 'Método inválido'], 405);
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    responderJson(['ok' => false, 'erro' => 'ID inválido'], 400);
}

try {

    $pdo->beginTransaction();

    // 🔒 Bloqueia a linha da empresa para evitar duas duplicações simultâneas furando o limite.
    $stmtEmpresa = $pdo->prepare("\n        SELECT \n            e.id,\n            e.status,\n            COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos,\n            e.proximo_numero_orcamento,\n            p.nome AS plano_nome,\n            p.limite_orcamentos\n        FROM empresas e\n        JOIN planos p ON p.id = e.plano_id\n        WHERE e.id = ?\n        LIMIT 1\n        FOR UPDATE\n    ");
    $stmtEmpresa->execute([$empresa_id]);
    $empresa = $stmtEmpresa->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('Empresa inválida');
    }

    if (($empresa['status'] ?? '') !== 'ativa') {
        responderJson(['ok' => false, 'erro' => 'Empresa suspensa. Entre em contato com o suporte.'], 403);
    }

    // ================= LIMITE DO PLANO =================
    $limitePlano = (int)($empresa['limite_orcamentos'] ?? 20);
    $limiteExtra = max(0, (int)($empresa['limite_extra_orcamentos'] ?? 0));
    $limiteTotal = $limitePlano + $limiteExtra;
    $planoNome   = $empresa['plano_nome'] ?? 'Plano';

    $stmtQtd = $pdo->prepare("\n        SELECT COUNT(*)\n        FROM orcamentos\n        WHERE empresa_id = ?\n        AND deleted_at IS NULL\n        AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')\n    ");
    $stmtQtd->execute([$empresa_id]);
    $qtdMes = (int)$stmtQtd->fetchColumn();

    if ($qtdMes >= $limiteTotal) {
        throw new Exception("Limite de orçamentos do plano {$planoNome} atingido ({$limiteTotal}/mês)");
    }

    // ================= ORÇAMENTO ORIGINAL =================
    $stmt = $pdo->prepare("\n        SELECT *\n        FROM orcamentos\n        WHERE id = ?\n        AND empresa_id = ?\n        AND deleted_at IS NULL\n        LIMIT 1\n    ");
    $stmt->execute([$id, $empresa_id]);
    $orc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orc) {
        throw new Exception('Orçamento não encontrado');
    }

    // ================= ITENS =================
    $stmt = $pdo->prepare("\n        SELECT\n            nome_snapshot,\n            preco_snapshot,\n            quantidade,\n            total\n        FROM orcamento_itens\n        WHERE orcamento_id = ?\n        AND empresa_id = ?\n        ORDER BY id ASC\n    ");
    $stmt->execute([$id, $empresa_id]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        throw new Exception('Orçamento sem itens para duplicar');
    }

    // Recalcula o total pelos itens para evitar copiar valor antigo inconsistente.
    $total = 0.0;

    foreach ($itens as $item) {
        $preco = (float)($item['preco_snapshot'] ?? 0);
        $qtd   = (int)($item['quantidade'] ?? 0);
        $sub   = (float)($item['total'] ?? ($preco * $qtd));

        if ($qtd <= 0 || $sub < 0) {
            continue;
        }

        $total += $sub;
    }

    if ($total < 0) {
        throw new Exception('Total inválido');
    }

    // ================= NOVO ORÇAMENTO =================
    $numero_orcamento = max(1, (int)($empresa['proximo_numero_orcamento'] ?? 1));
    $token = bin2hex(random_bytes(32));
    $validade = date('Y-m-d', strtotime('+7 days'));

    $stmt = $pdo->prepare("
        INSERT INTO orcamentos (
            empresa_id,
            numero,
            cliente_id,
            cliente_nome,
            cliente_whatsapp,
            valor_total,
            status,
            token,
            validade,
            observacoes,
            criado_por
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?)
    ");

    $stmt->execute([
        $empresa_id,
        $numero_orcamento,
        $orc['cliente_id'] ?: null,
        $orc['cliente_nome'],
        $orc['cliente_whatsapp'],
        $total,
        'rascunho',
        $token,
        $validade,
        $orc['observacoes'],
        $usuario_id
    ]);

    $novo_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        UPDATE empresas
        SET proximo_numero_orcamento = ?
        WHERE id = ?
    ");
    $stmt->execute([$numero_orcamento + 1, $empresa_id]);

    // ================= DUPLICA ITENS =================
    $stmtItem = $pdo->prepare("\n        INSERT INTO orcamento_itens (\n            orcamento_id,\n            empresa_id,\n            nome_snapshot,\n            preco_snapshot,\n            quantidade,\n            total\n        ) VALUES (?,?,?,?,?,?)\n    ");

    $itensDuplicados = 0;

    foreach ($itens as $item) {
        $nome  = trim((string)($item['nome_snapshot'] ?? ''));
        $preco = (float)($item['preco_snapshot'] ?? 0);
        $qtd   = (int)($item['quantidade'] ?? 0);
        $sub   = (float)($item['total'] ?? ($preco * $qtd));

        if ($nome === '' || $preco < 0 || $qtd <= 0 || $sub < 0) {
            continue;
        }

        $stmtItem->execute([
            $novo_id,
            $empresa_id,
            $nome,
            $preco,
            $qtd,
            $sub
        ]);

        $itensDuplicados++;
    }

    if ($itensDuplicados === 0) {
        throw new Exception('Nenhum item válido para duplicar');
    }

    $pdo->commit();

    responderJson([
        'ok' => true,
        'id' => $novo_id,
        'numero' => $numero_orcamento,
        'token' => $token,
        'status' => 'rascunho',
        'total' => $total,
        'itens' => $itensDuplicados,
        'limite' => [
            'plano' => $limitePlano,
            'extra' => $limiteExtra,
            'total' => $limiteTotal,
            'usados_mes_antes' => $qtdMes,
            'usados_mes_depois' => $qtdMes + 1
        ]
    ]);

} catch (Throwable $e) {

    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[orcamentos_duplicar] ' . $e->getMessage());

    responderJson([
        'ok' => false,
        'erro' => $e->getMessage()
    ], 500);
}
