<?php
/**
 * PDF do orçamento
 *
 * Modos de acesso:
 * - Interno/autenticado: /pdf_orcamento.php?id=123
 * - Público por token:  /pdf_orcamento.php?t=TOKEN
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

ini_set('display_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');

// ================= HELPERS =================
function pdf_h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function pdf_money($value): string {
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

function pdf_date_br($value): string {
    if (empty($value)) {
        return '-';
    }

    $ts = strtotime((string)$value);
    return $ts ? date('d/m/Y', $ts) : '-';
}

function pdf_status_label(string $status): string {
    $labels = [
        'rascunho'     => 'Rascunho',
        'enviado'      => 'Enviado',
        'visualizado'  => 'Visualizado',
        'aprovado'     => 'Aprovado',
        'recusado'     => 'Recusado',
    ];

    return $labels[$status] ?? ucfirst($status);
}

function pdf_base_url(): string {
    if (function_exists('app_base_url')) {
        return rtrim(app_base_url(), '/');
    }

    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );

    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

    return $scheme . '://' . $host;
}

function pdf_image_data_uri(string $path): string {
    if (!is_file($path)) {
        return '';
    }

    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    $mime = match ($ext) {
        'jpg', 'jpeg' => 'image/jpeg',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        default       => 'image/png',
    };

    $data = file_get_contents($path);
    if ($data === false) {
        return '';
    }

    return 'data:' . $mime . ';base64,' . base64_encode($data);
}

function pdf_normalizar_telefone($telefone): string {
    return preg_replace('/\D+/', '', (string)$telefone);
}

function pdf_formatar_telefone($telefone): string {
    $n = pdf_normalizar_telefone($telefone);

    if (strlen($n) === 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $n);
    }

    if (strlen($n) === 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $n);
    }

    return $n ?: '-';
}

function pdf_responder_erro(string $mensagem, int $status = 404): void {
    http_response_code($status);
    echo pdf_h($mensagem);
    exit;
}

// ================= ENTRADA =================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$token = trim((string)($_GET['t'] ?? $_GET['token'] ?? ''));

$acessoPublico = $token !== '';

if (!$acessoPublico && $id <= 0) {
    pdf_responder_erro('Orçamento não informado.', 400);
}

// ================= ORÇAMENTO =================
try {
    if ($acessoPublico) {
        $stmt = $pdo->prepare("\n            SELECT\n                o.*,\n                e.nome AS empresa_nome,\n                e.logo AS empresa_logo,\n                e.whatsapp AS empresa_whatsapp,\n                e.status AS empresa_status\n            FROM orcamentos o\n            INNER JOIN empresas e ON e.id = o.empresa_id\n            WHERE o.token = ?\n            AND o.deleted_at IS NULL\n            LIMIT 1\n        ");
        $stmt->execute([$token]);
    } else {
        checkAuth();

        $empresa_id = (int)empresa_id();

        if ($empresa_id <= 0) {
            pdf_responder_erro('Empresa não identificada.', 403);
        }

        $stmt = $pdo->prepare("\n            SELECT\n                o.*,\n                e.nome AS empresa_nome,\n                e.logo AS empresa_logo,\n                e.whatsapp AS empresa_whatsapp,\n                e.status AS empresa_status\n            FROM orcamentos o\n            INNER JOIN empresas e ON e.id = o.empresa_id\n            WHERE o.id = ?\n            AND o.empresa_id = ?\n            AND o.deleted_at IS NULL\n            LIMIT 1\n        ");
        $stmt->execute([$id, $empresa_id]);
    }

    $orc = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$orc) {
        pdf_responder_erro('Orçamento não encontrado.');
    }

    if (($orc['empresa_status'] ?? '') !== 'ativa') {
        pdf_responder_erro('Empresa suspensa. Entre em contato com o suporte.', 403);
    }

    $stmt = $pdo->prepare("\n        SELECT id, nome_snapshot, preco_snapshot, quantidade, total\n        FROM orcamento_itens\n        WHERE orcamento_id = ?\n        AND empresa_id = ?\n        ORDER BY id ASC\n    ");
    $stmt->execute([(int)$orc['id'], (int)$orc['empresa_id']]);
    $itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$itens) {
        pdf_responder_erro('Orçamento sem itens.', 422);
    }
} catch (Throwable $e) {
    error_log('[PDF_ORCAMENTO] ' . $e->getMessage());
    pdf_responder_erro('Erro interno ao gerar o PDF.', 500);
}

// ================= LINKS =================
$baseUrl = pdf_base_url();
$linkAprovacao = $baseUrl . '/publico.php?t=' . rawurlencode((string)$orc['token']);

$telefoneCliente = pdf_normalizar_telefone($orc['cliente_whatsapp'] ?? '');
$numeroPublico = (int)($orc['numero'] ?? $orc['id']);
$msgWhatsapp = "Olá " . ($orc['cliente_nome'] ?? '') . ", segue sua proposta #" . $numeroPublico . ": " . $linkAprovacao;
$whatsapp = $telefoneCliente
    ? 'https://api.whatsapp.com/send?phone=55' . $telefoneCliente . '&text=' . rawurlencode($msgWhatsapp)
    : '';

// ================= IMAGENS =================
$marcaSistema = pdf_image_data_uri(__DIR__ . '/imagens/orcamentaria_express.png');
$logoEmpresa = trim((string)($orc['empresa_logo'] ?? ''));

if ($logoEmpresa !== '' && !preg_match('#^https?://#i', $logoEmpresa)) {
    $logoRelativo = ltrim($logoEmpresa, '/');
    $possiveis = [
        __DIR__ . '/' . $logoRelativo,
        dirname(__DIR__) . '/' . $logoRelativo,
        __DIR__ . '/imagens/' . basename($logoRelativo),
    ];

    foreach ($possiveis as $path) {
        if (is_file($path)) {
            $logoEmpresa = pdf_image_data_uri($path);
            break;
        }
    }
}

// ================= DADOS FORMATADOS =================
$status = (string)($orc['status'] ?? 'rascunho');
$statusLabel = pdf_status_label($status);
$orcId = (int)$orc['id'];
$orcNumero = (int)($orc['numero'] ?? $orcId);
$empresaNome = pdf_h($orc['empresa_nome'] ?? '');
$clienteNome = pdf_h($orc['cliente_nome'] ?? '');
$clienteWhatsapp = pdf_h(pdf_formatar_telefone($orc['cliente_whatsapp'] ?? ''));
$empresaWhatsapp = pdf_h(pdf_formatar_telefone($orc['empresa_whatsapp'] ?? ''));
$dataCriacao = pdf_date_br($orc['created_at'] ?? null);
$dataValidade = pdf_date_br($orc['validade'] ?? null);
$totalOrcamento = pdf_money($orc['valor_total'] ?? 0);
$observacoes = trim((string)($orc['observacoes'] ?? ''));

// ================= HTML =================
$html = '<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 24px 26px; }

    body {
        margin: 0;
        background: #ffffff;
        font-family: DejaVu Sans, Arial, sans-serif;
        color: #1f2933;
        font-size: 12px;
        line-height: 1.45;
    }

    .header {
        width: 100%;
        border-bottom: 3px solid #111827;
        padding-bottom: 14px;
        margin-bottom: 18px;
    }

    .header td {
        border: 0;
        padding: 0;
        vertical-align: top;
    }

    .brand-wrap {
        display: block;
    }

    .brand-system {
        max-height: 46px;
        max-width: 210px;
        margin-bottom: 8px;
    }

    .brand-company {
        max-height: 58px;
        max-width: 210px;
        margin-bottom: 6px;
    }

    .company-name {
        font-size: 17px;
        font-weight: bold;
        color: #111827;
    }

    .muted {
        color: #64748b;
        font-size: 11px;
    }

    .doc-title {
        font-size: 24px;
        font-weight: bold;
        color: #111827;
        margin: 0 0 4px 0;
    }

    .badge {
        display: inline-block;
        padding: 4px 9px;
        border-radius: 999px;
        background: #eef2f7;
        color: #111827;
        font-size: 10px;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: .04em;
    }

    .box {
        border: 1px solid #d9e2ec;
        border-radius: 8px;
        padding: 12px 14px;
        margin: 13px 0;
        background: #fbfdff;
    }

    .info-table {
        width: 100%;
        border-collapse: collapse;
        margin: 0;
    }

    .info-table td {
        border: 0;
        padding: 2px 0;
    }

    .label {
        color: #64748b;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .value {
        font-weight: bold;
        color: #111827;
    }

    .items {
        width: 100%;
        border-collapse: collapse;
        margin-top: 16px;
    }

    .items th {
        background: #111827;
        color: #ffffff;
        font-size: 11px;
        padding: 9px 8px;
        border: 1px solid #111827;
        text-align: left;
    }

    .items td {
        padding: 8px;
        border: 1px solid #e5e7eb;
        vertical-align: top;
    }

    .items tr:nth-child(even) td {
        background: #f8fafc;
    }

    .center { text-align: center; }
    .right { text-align: right; }

    .total-box {
        margin-top: 16px;
        text-align: right;
    }

    .total-label {
        color: #64748b;
        font-size: 12px;
    }

    .total-value {
        font-size: 24px;
        font-weight: bold;
        color: #111827;
    }

    .approval {
        margin-top: 20px;
        padding: 14px;
        border: 1px solid #d9e2ec;
        border-radius: 8px;
        text-align: center;
        background: #f8fafc;
    }

    .btn {
        display: inline-block;
        padding: 10px 16px;
        border-radius: 6px;
        background: #16a34a;
        color: #ffffff;
        font-weight: bold;
        text-decoration: none;
        margin: 4px 2px;
    }

    .btn-whatsapp {
        background: #128c7e;
    }

    .link-text {
        margin-top: 8px;
        color: #475569;
        font-size: 10px;
        word-break: break-all;
    }

    .obs {
        margin-top: 14px;
        padding: 12px 14px;
        border-left: 4px solid #111827;
        background: #f8fafc;
    }

    .footer {
        margin-top: 28px;
        padding-top: 12px;
        border-top: 1px solid #e5e7eb;
        color: #64748b;
        font-size: 10px;
        text-align: center;
    }
</style>
</head>
<body>';

$html .= '<table class="header">
    <tr>
        <td style="width: 55%;">
            <div class="brand-wrap">';

if ($logoEmpresa !== '') {
    $html .= '<img class="brand-company" src="' . pdf_h($logoEmpresa) . '" alt="Logo da empresa"><br>';
} elseif ($marcaSistema !== '') {
    $html .= '<img class="brand-system" src="' . pdf_h($marcaSistema) . '" alt="Orçamentaria Express"><br>';
}

$html .= '<div class="company-name">' . $empresaNome . '</div>';

if ($empresaWhatsapp !== '-') {
    $html .= '<div class="muted">WhatsApp: ' . $empresaWhatsapp . '</div>';
}

$html .= '  </div>
        </td>
        <td style="width: 45%; text-align: right;">
            <div class="doc-title">Proposta Comercial</div>
            <div style="margin-bottom: 8px;"><span class="badge">' . pdf_h($statusLabel) . '</span></div>
            <div><strong>Orçamento:</strong> #' . $orcNumero . '</div>
            <div><strong>Data:</strong> ' . pdf_h($dataCriacao) . '</div>
            <div><strong>Validade:</strong> ' . pdf_h($dataValidade) . '</div>
        </td>
    </tr>
</table>';

$html .= '<div class="box">
    <table class="info-table">
        <tr>
            <td style="width: 50%;">
                <div class="label">Cliente</div>
                <div class="value">' . $clienteNome . '</div>
            </td>
            <td style="width: 50%;">
                <div class="label">WhatsApp</div>
                <div class="value">' . $clienteWhatsapp . '</div>
            </td>
        </tr>
    </table>
</div>';

$html .= '<table class="items">
    <thead>
        <tr>
            <th>Item / Serviço</th>
            <th style="width: 70px;" class="center">Qtd</th>
            <th style="width: 115px;" class="right">Valor unit.</th>
            <th style="width: 115px;" class="right">Total</th>
        </tr>
    </thead>
    <tbody>';

foreach ($itens as $item) {
    $html .= '<tr>
        <td>' . pdf_h($item['nome_snapshot'] ?? '') . '</td>
        <td class="center">' . (int)($item['quantidade'] ?? 0) . '</td>
        <td class="right">' . pdf_money($item['preco_snapshot'] ?? 0) . '</td>
        <td class="right">' . pdf_money($item['total'] ?? 0) . '</td>
    </tr>';
}

$html .= '</tbody></table>';

$html .= '<div class="total-box">
    <div class="total-label">Valor total da proposta</div>
    <div class="total-value">' . pdf_h($totalOrcamento) . '</div>
</div>';

if ($observacoes !== '') {
    $html .= '<div class="obs">
        <div class="label">Observações</div>
        <div>' . nl2br(pdf_h($observacoes)) . '</div>
    </div>';
}

$html .= '<div class="approval">';

if (!in_array($status, ['aprovado', 'recusado'], true)) {
    $html .= '<div style="font-weight:bold; margin-bottom:8px;">Acesse o link abaixo para aprovar ou recusar esta proposta:</div>
        <a class="btn" href="' . pdf_h($linkAprovacao) . '">Abrir orçamento</a>';
} else {
    $html .= '<div style="font-weight:bold; margin-bottom:8px;">Esta proposta está com status: ' . pdf_h($statusLabel) . '.</div>
        <a class="btn" href="' . pdf_h($linkAprovacao) . '">Ver orçamento</a>';
}

if ($whatsapp !== '') {
    $html .= ' <a class="btn btn-whatsapp" href="' . pdf_h($whatsapp) . '">Falar no WhatsApp</a>';
}

$html .= '<div class="link-text">' . pdf_h($linkAprovacao) . '</div>
</div>';

$html .= '<div class="footer">
    Proposta gerada automaticamente pelo sistema Orçamentaria Express.<br>
    Os valores e condições são válidos até a data informada nesta proposta.
</div>';

$html .= '</body></html>';

// ================= PDF =================
try {
    $options = new Options();
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('isHtml5ParserEnabled', true);

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    $filename = 'orcamento-' . $orcNumero . '.pdf';
    $dompdf->stream($filename, ['Attachment' => false]);
} catch (Throwable $e) {
    error_log('[PDF_ORCAMENTO_RENDER] ' . $e->getMessage());
    pdf_responder_erro('Erro ao renderizar o PDF.', 500);
}
