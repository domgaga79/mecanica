<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/empresa.php';

header('Content-Type: application/json; charset=utf-8');

checkAuth();

$empresa_id = empresa_id();
$usuario_id = usuario_id();

if (!$empresa_id) {
    echo json_encode(['ok'=>false,'erro'=>"Empresa não identificada"]);
    exit;
}

/**
 * Monta a URL base pública do sistema sem deixar o domínio fixo no código.
 */
function orcamento_base_url(): string
{
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'mecanica.bacuridigital.com';

    return $scheme . '://' . $host;
}

function orcamento_normalizar_utf8(?string $str): string
{
    if ($str === null) {
        return '';
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
    }

    return $str;
}

function orcamento_link_whatsapp(string $telefone, string $cliente, int $numero, float $valor, string $token): ?string
{
    $telefone = preg_replace('/\D/', '', $telefone);

    if ($telefone === '') {
        return null;
    }

    // Se o número vier sem código do país, assume Brasil.
    if (strlen($telefone) <= 11) {
        $telefone = '55' . $telefone;
    }

    $cliente = orcamento_normalizar_utf8($cliente);
    $valorFormatado = number_format($valor, 2, ',', '.');
    $linkPublico = orcamento_base_url() . '/publico.php?t=' . urlencode($token);

    $msg  = "🧾 *Orçamento #{$numero}*\n\n";
    $msg .= "👤 Cliente: {$cliente}\n";
    $msg .= "💰 Valor: R$ {$valorFormatado}\n\n";
    $msg .= "🔗 Acesse seu orçamento:\n{$linkPublico}\n\n";
    $msg .= "✔ Você pode aprovar ou recusar diretamente no link.\n\n";
    $msg .= "Qualquer dúvida, estamos à disposição!";

    return 'https://api.whatsapp.com/send?phone=' . $telefone . '&text=' . rawurlencode($msg);
}

try {

    // ================= VALIDA MÉTODO =================
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    // ================= PLANO =================
    // O plano define a cota base.
    // A coluna empresas.limite_extra_orcamentos acrescenta uma cota manual por empresa.
    $stmt = $pdo->prepare("
        SELECT 
            p.nome AS plano_nome,
            p.limite_orcamentos,
            COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos
        FROM empresas e
        JOIN planos p ON p.id = e.plano_id
        WHERE e.id = ?
        LIMIT 1
    ");
    $stmt->execute([$empresa_id]);

    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$empresa) {
        throw new Exception('Empresa inválida');
    }

    $plano       = $empresa['plano_nome'];
    $limitePlano = (int)($empresa['limite_orcamentos'] ?? 20);
    $limiteExtra = max(0, (int)($empresa['limite_extra_orcamentos'] ?? 0));
    $limite      = $limitePlano + $limiteExtra;

    // ================= INPUT =================
    $cliente  = trim($_POST['cliente'] ?? '');
    $whatsapp = preg_replace('/\D/', '', $_POST['whatsapp'] ?? '');
    $itensRaw = $_POST['itens'] ?? '';

    if ($cliente === '' || $itensRaw === '') {
        throw new Exception('Dados inválidos');
    }

    $itens = json_decode($itensRaw, true);

    if (!is_array($itens)) {
        throw new Exception('JSON de itens inválido');
    }

    // ================= PROCESSAMENTO =================
    $total = 0;
    $validade = date('Y-m-d', strtotime('+7 days'));

    $pdo->beginTransaction();

    // 🔒 LOCK EMPRESA + RESERVA DO NÚMERO SEQUENCIAL POR EMPRESA
    $stmtEmpresaLock = $pdo->prepare("
        SELECT proximo_numero_orcamento
        FROM empresas
        WHERE id = ?
        LIMIT 1
        FOR UPDATE
    ");
    $stmtEmpresaLock->execute([$empresa_id]);
    $empresaLock = $stmtEmpresaLock->fetch(PDO::FETCH_ASSOC);

    if (!$empresaLock) {
        throw new Exception('Empresa inválida');
    }

    $numero_orcamento = max(1, (int)($empresaLock['proximo_numero_orcamento'] ?? 1));

    // 🔒 LIMITE
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM orcamentos
        WHERE empresa_id = ?
        AND deleted_at IS NULL
        AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    ");
    $stmt->execute([$empresa_id]);

    $qtdMes = (int)$stmt->fetchColumn();

    if ($qtdMes >= $limite) {
        throw new Exception("Limite do plano atingido ({$limite}/mês)");
    }

    // 🔒 TOKEN
    $token = bin2hex(random_bytes(32));

    // ================= CRIA ORÇAMENTO =================
    $stmt = $pdo->prepare("
        INSERT INTO orcamentos 
        (empresa_id, numero, cliente_nome, cliente_whatsapp, valor_total, token, status, validade, criado_por)
        VALUES (?,?,?,?,?,?, 'enviado', ?, ?)
    ");

    $stmt->execute([
        $empresa_id,
        $numero_orcamento,
        $cliente,
        $whatsapp,
        0,
        $token,
        $validade,
        $usuario_id
    ]);

    $orcamento_id = (int)$pdo->lastInsertId();

    $stmt = $pdo->prepare("
        UPDATE empresas
        SET proximo_numero_orcamento = ?
        WHERE id = ?
    ");
    $stmt->execute([$numero_orcamento + 1, $empresa_id]);

    // ================= ITENS =================
    $itens_validos = 0;

    $stmtItem = $pdo->prepare("
        INSERT INTO orcamento_itens (
            empresa_id,
            orcamento_id,
            nome_snapshot,
            preco_snapshot,
            quantidade,
            total
        ) VALUES (?,?,?,?,?,?)
    ");

    foreach ($itens as $i) {

        $nome  = trim($i['nome'] ?? '');
        $preco = (float)($i['preco'] ?? 0);
        $qtd   = (int)($i['qtd'] ?? 1);

        if ($nome === '' || $preco < 0 || $qtd <= 0) {
            continue;
        }

        $sub = $preco * $qtd;

        $stmtItem->execute([
            $empresa_id,
            $orcamento_id,
            $nome,
            $preco,
            $qtd,
            $sub
        ]);

        $total += $sub;
        $itens_validos++;
    }

    if ($itens_validos === 0) {
        throw new Exception('Nenhum item válido');
    }

    // ================= UPDATE TOTAL =================
    $pdo->prepare("
        UPDATE orcamentos 
        SET valor_total = ?
        WHERE id = ?
        AND empresa_id = ?
        AND deleted_at IS NULL
    ")->execute([$total, $orcamento_id, $empresa_id]);

    $pdo->commit();

    $urlPublica = orcamento_base_url() . '/publico.php?t=' . urlencode($token);
    $urlPdf = orcamento_base_url() . '/pdf_orcamento.php?id=' . $orcamento_id;
    $urlWhatsapp = orcamento_link_whatsapp($whatsapp, $cliente, $numero_orcamento, $total, $token);

    echo json_encode([
        'ok' => true,
        'id' => $orcamento_id,
        'numero' => $numero_orcamento,
        'token' => $token,
        'total' => $total,
        'itens' => $itens_validos,
        'plano' => $plano,
        'limite_orcamentos' => $limite,
        'url_publica' => $urlPublica,
        'pdf' => $urlPdf,
        'whatsapp' => $urlWhatsapp
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'ok' => false,
        'erro' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
