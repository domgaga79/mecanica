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

    $empresa_id = empresa_id();
    $usuario_id = usuario_id();

    if (!$empresa_id) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Empresa não identificada']);
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'erro' => 'Método inválido']);
        exit;
    }

    $id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $status = trim((string)($_POST['status'] ?? ''));

    if (!$id || $status === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Dados inválidos']);
        exit;
    }

    $permitidos = ['rascunho', 'enviado', 'visualizado', 'aprovado', 'recusado'];

    if (!in_array($status, $permitidos, true)) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'erro' => 'Status inválido']);
        exit;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("\n        SELECT *\n        FROM orcamentos\n        WHERE id = ?\n        AND empresa_id = ?\n        AND deleted_at IS NULL\n        LIMIT 1\n        FOR UPDATE\n    ");
    $stmt->execute([$id, $empresa_id]);
    $orc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orc) {
        throw new Exception('Orçamento não encontrado');
    }

    $statusAtual = $orc['status'];

    if (in_array($statusAtual, ['aprovado', 'recusado'], true)) {
        throw new Exception('Orçamento já finalizado');
    }

    $ordem = [
        'rascunho' => 1,
        'enviado' => 2,
        'visualizado' => 3,
        'aprovado' => 4,
        'recusado' => 4,
    ];

    if (($ordem[$status] ?? 0) < ($ordem[$statusAtual] ?? 0)) {
        throw new Exception('Não é permitido voltar status');
    }

    $aprovado_em = null;
    $aprovado_por = null;

    if ($status === 'aprovado') {
        $aprovado_em = date('Y-m-d H:i:s');
        $aprovado_por = $usuario_id;
    }

    $stmt = $pdo->prepare("\n        UPDATE orcamentos\n        SET status = ?,\n            aprovado_em = COALESCE(?, aprovado_em),\n            aprovado_por = COALESCE(?, aprovado_por)\n        WHERE id = ?\n        AND empresa_id = ?\n        AND deleted_at IS NULL\n    ");
    $stmt->execute([$status, $aprovado_em, $aprovado_por, $id, $empresa_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('Falha ao atualizar orçamento');
    }

    if ($status === 'aprovado') {
        $cliente_id = $orc['cliente_id'];

        if (!$cliente_id && !empty($orc['cliente_nome'])) {
            $stmt = $pdo->prepare("\n                SELECT id\n                FROM clientes\n                WHERE empresa_id = ?\n                AND nome = ?\n                AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $stmt->execute([$empresa_id, $orc['cliente_nome']]);
            $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cliente) {
                $cliente_id = (int)$cliente['id'];
            } else {
                $stmt = $pdo->prepare("\n                    INSERT INTO clientes (empresa_id, nome, whatsapp)\n                    VALUES (?, ?, ?)\n                ");
                $stmt->execute([
                    $empresa_id,
                    $orc['cliente_nome'],
                    $orc['cliente_whatsapp'],
                ]);

                $cliente_id = (int)$pdo->lastInsertId();
            }

            $stmt = $pdo->prepare("\n                UPDATE orcamentos\n                SET cliente_id = ?\n                WHERE id = ?\n                AND empresa_id = ?\n                AND deleted_at IS NULL\n            ");
            $stmt->execute([$cliente_id, $id, $empresa_id]);
        }

        $stmt = $pdo->prepare("\n            SELECT nome_snapshot, preco_snapshot\n            FROM orcamento_itens\n            WHERE orcamento_id = ?\n            AND empresa_id = ?\n        ");
        $stmt->execute([$id, $empresa_id]);
        $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($itens as $item) {
            $nome = trim((string)($item['nome_snapshot'] ?? ''));

            if ($nome === '') {
                continue;
            }

            $stmt = $pdo->prepare("\n                SELECT id\n                FROM produtos\n                WHERE empresa_id = ?\n                AND nome = ?\n                AND deleted_at IS NULL\n                LIMIT 1\n            ");
            $stmt->execute([$empresa_id, $nome]);
            $prod = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$prod) {
                $stmt = $pdo->prepare("\n                    INSERT INTO produtos (empresa_id, nome, preco)\n                    VALUES (?, ?, ?)\n                ");
                $stmt->execute([
                    $empresa_id,
                    $nome,
                    (float)($item['preco_snapshot'] ?? 0),
                ]);
            }
        }
    }

    $pdo->commit();

    $url_whatsapp = null;

    if ($status === 'enviado') {
        $baseUrl = app_base_url();
        $urlPublica = $baseUrl . '/publico.php?t=' . rawurlencode((string)$orc['token']);
        $telefone = preg_replace('/\D/', '', (string)($orc['cliente_whatsapp'] ?? ''));

        if ($telefone !== '') {
            $numeroExibicao = (int)($orc['numero'] ?? $orc['id']);
            $mensagem = "🧾 Orçamento #{$numeroExibicao}\n\n";
            $mensagem .= "👤 Cliente: {$orc['cliente_nome']}\n";
            $mensagem .= "💰 Valor: R$ " . number_format((float)$orc['valor_total'], 2, ',', '.') . "\n\n";
            $mensagem .= "🔗 Acesse seu orçamento:\n" . $urlPublica . "\n\n";
            $mensagem .= "✔ Você pode aprovar ou recusar diretamente no link.";

            $url_whatsapp = 'https://api.whatsapp.com/send?phone=55' . $telefone . '&text=' . rawurlencode($mensagem);
        }
    }

    echo json_encode([
        'ok' => true,
        'status' => $status,
        'url_whatsapp' => $url_whatsapp,
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage(),
    ]);
}
