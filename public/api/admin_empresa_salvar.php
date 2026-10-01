<?php
ob_start();

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json');

checkAuthApi();

function responderAdminEmpresa(array $payload, int $statusCode = 200): void
{
    if (ob_get_length()) {
        ob_clean();
    }

    http_response_code($statusCode);
    echo json_encode($payload);
    exit;
}

function painelSuperAdminApi(PDO $pdo): bool
{
    if (!function_exists('isAdmin') || !isAdmin()) {
        return false;
    }

    $empresaAtual = (int)empresa_id();
    if ($empresaAtual <= 0) {
        return false;
    }

    $stmt = $pdo->prepare("SELECT slug FROM empresas WHERE id = ? LIMIT 1");
    $stmt->execute([$empresaAtual]);
    $slug = (string)$stmt->fetchColumn();

    // Restrição prática para a estrutura atual: somente a empresa-mãe Bacuri Digital gerencia clientes.
    return $slug === 'bacuri-digital';
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        responderAdminEmpresa(['ok' => false, 'erro' => 'Método inválido'], 405);
    }

    if (!painelSuperAdminApi($pdo)) {
        responderAdminEmpresa(['ok' => false, 'erro' => 'Acesso negado'], 403);
    }

    $empresaId = isset($_POST['empresa_id']) ? (int)$_POST['empresa_id'] : 0;
    $planoId = isset($_POST['plano_id']) ? (int)$_POST['plano_id'] : 0;
    $status = trim($_POST['status'] ?? '');
    $limiteExtra = isset($_POST['limite_extra_orcamentos']) ? (int)$_POST['limite_extra_orcamentos'] : 0;

    if ($empresaId <= 0) {
        throw new Exception('Empresa inválida');
    }

    if ($planoId <= 0) {
        throw new Exception('Plano inválido');
    }

    if (!in_array($status, ['ativa', 'suspensa'], true)) {
        throw new Exception('Status inválido');
    }

    if ($limiteExtra < 0) {
        throw new Exception('Limite extra não pode ser negativo');
    }

    if ($limiteExtra > 1000000) {
        throw new Exception('Limite extra muito alto');
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, nome FROM empresas WHERE id = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('Empresa não encontrada');
    }

    $stmt = $pdo->prepare("SELECT id, nome FROM planos WHERE id = ? AND ativo = 1 LIMIT 1");
    $stmt->execute([$planoId]);
    $plano = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$plano) {
        throw new Exception('Plano não encontrado ou inativo');
    }

    $stmt = $pdo->prepare("
        UPDATE empresas
        SET
            plano_id = ?,
            assinatura = ?,
            status = ?,
            limite_extra_orcamentos = ?
        WHERE id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $planoId,
        $plano['nome'],
        $status,
        $limiteExtra,
        $empresaId
    ]);

    $pdo->commit();

    responderAdminEmpresa([
        'ok' => true,
        'mensagem' => 'Empresa atualizada com sucesso',
        'empresa_id' => $empresaId,
        'plano' => $plano['nome'],
        'status' => $status,
        'limite_extra_orcamentos' => $limiteExtra
    ]);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Erro admin_empresa_salvar.php: ' . $e->getMessage());

    responderAdminEmpresa([
        'ok' => false,
        'erro' => $e->getMessage()
    ], 500);
}
