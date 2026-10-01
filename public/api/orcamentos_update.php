<?php
/**
 * Atualiza orçamento existente.
 *
 * Regras principais:
 * - exige autenticação interna;
 * - restringe por empresa logada;
 * - bloqueia edição de orçamento aprovado/recusado;
 * - aceita item com valor zero, mas nunca valor negativo;
 * - recalcula o total exclusivamente a partir dos itens enviados;
 * - mantém histórico por snapshot em orcamento_itens.
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json; charset=utf-8');

checkAuthApi();

function resposta_json(bool $ok, array $dados = [], int $status = 200): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $ok], $dados), JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizar_texto(string $texto, int $limite = 150): string
{
    $texto = trim(preg_replace('/\s+/', ' ', $texto));

    if (function_exists('mb_substr')) {
        return mb_substr($texto, 0, $limite, 'UTF-8');
    }

    return substr($texto, 0, $limite);
}

function ler_payload(): array
{
    $raw = file_get_contents('php://input');
    $data = [];

    if ($raw !== false && trim($raw) !== '') {
        $json = json_decode($raw, true);

        if (is_array($json)) {
            $data = $json;
        }
    }

    if (!$data) {
        $data = $_POST;
    }

    if (isset($data['itens']) && is_string($data['itens'])) {
        $itens = json_decode($data['itens'], true);
        $data['itens'] = is_array($itens) ? $itens : [];
    }

    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    resposta_json(false, ['erro' => 'Método inválido'], 405);
}

$empresa_id = (int)empresa_id();
$usuario_id = (int)usuario_id();

if ($empresa_id <= 0) {
    resposta_json(false, ['erro' => 'Empresa não identificada'], 401);
}

$data = ler_payload();

$id       = (int)($data['id'] ?? 0);
$cliente  = normalizar_texto((string)($data['cliente'] ?? ''), 150);
$whatsapp = preg_replace('/\D/', '', (string)($data['whatsapp'] ?? ''));
$itens    = $data['itens'] ?? [];

if ($id <= 0) {
    resposta_json(false, ['erro' => 'Orçamento inválido'], 400);
}

if ($cliente === '') {
    resposta_json(false, ['erro' => 'Informe o nome do cliente'], 400);
}

if (!is_array($itens) || count($itens) === 0) {
    resposta_json(false, ['erro' => 'Informe pelo menos um item'], 400);
}

$itensLimpos = [];
$total = 0.0;

foreach ($itens as $item) {
    if (!is_array($item)) {
        continue;
    }

    $nome = normalizar_texto((string)($item['nome'] ?? ''), 150);
    $preco = (float)str_replace(',', '.', (string)($item['preco'] ?? 0));
    $qtd = (int)($item['qtd'] ?? $item['quantidade'] ?? 0);

    if ($nome === '' || $preco < 0 || $qtd <= 0) {
        continue;
    }

    if ($preco > 99999999.99 || $qtd > 9999) {
        resposta_json(false, ['erro' => 'Item com preço ou quantidade fora do limite permitido'], 400);
    }

    $sub = round($preco * $qtd, 2);

    if ($sub > 99999999.99) {
        resposta_json(false, ['erro' => 'Subtotal de item fora do limite permitido'], 400);
    }

    $total = round($total + $sub, 2);

    if ($total > 99999999.99) {
        resposta_json(false, ['erro' => 'Total do orçamento fora do limite permitido'], 400);
    }

    $itensLimpos[] = [
        'nome'  => $nome,
        'preco' => round($preco, 2),
        'qtd'   => $qtd,
        'total' => $sub,
    ];
}

if (count($itensLimpos) === 0) {
    resposta_json(false, ['erro' => 'Nenhum item válido'], 400);
}

try {
    $pdo->beginTransaction();

    // Bloqueia o orçamento para evitar edição concorrente.
    $stmt = $pdo->prepare("\n        SELECT id, status\n        FROM orcamentos\n        WHERE id = ?\n        AND empresa_id = ?\n        AND deleted_at IS NULL\n        LIMIT 1\n        FOR UPDATE\n    ");
    $stmt->execute([$id, $empresa_id]);
    $orcamento = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orcamento) {
        throw new RuntimeException('Orçamento não encontrado', 404);
    }

    if (in_array($orcamento['status'], ['aprovado', 'recusado'], true)) {
        throw new RuntimeException('Orçamento finalizado não pode ser editado', 409);
    }

    // Remove os itens antigos somente depois que os novos já foram validados.
    $stmt = $pdo->prepare("\n        DELETE FROM orcamento_itens\n        WHERE orcamento_id = ?\n        AND empresa_id = ?\n    ");
    $stmt->execute([$id, $empresa_id]);

    $stmtItem = $pdo->prepare("\n        INSERT INTO orcamento_itens (\n            empresa_id,\n            orcamento_id,\n            nome_snapshot,\n            preco_snapshot,\n            quantidade,\n            total\n        ) VALUES (?,?,?,?,?,?)\n    ");

    foreach ($itensLimpos as $item) {
        $stmtItem->execute([
            $empresa_id,
            $id,
            $item['nome'],
            $item['preco'],
            $item['qtd'],
            $item['total'],
        ]);
    }

    $stmt = $pdo->prepare("\n        UPDATE orcamentos\n        SET cliente_nome = ?,\n            cliente_whatsapp = ?,\n            valor_total = ?\n        WHERE id = ?\n        AND empresa_id = ?\n        AND deleted_at IS NULL\n    ");
    $stmt->execute([
        $cliente,
        $whatsapp,
        $total,
        $id,
        $empresa_id,
    ]);

    $pdo->commit();

    resposta_json(true, [
        'id' => $id,
        'total' => $total,
        'itens' => count($itensLimpos),
        'editado_por' => $usuario_id ?: null,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    $codigo = (int)$e->getCode();
    $http = in_array($codigo, [400, 401, 403, 404, 409, 422], true) ? $codigo : 500;

    error_log('[orcamentos_update] ' . $e->getMessage());

    resposta_json(false, [
        'erro' => $e->getMessage(),
    ], $http);
}
