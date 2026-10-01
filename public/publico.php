<?php
require_once __DIR__ . '/../config/db.php';

$t = trim($_GET['t'] ?? '');

// ================= HELPERS =================
function e($v){
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}


function base_url(){
    $https = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
    $scheme = $https ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'mecanica.bacuridigital.com';
    return $scheme . '://' . $host;
}

function pagina_indisponivel($mensagem = 'Orçamento não encontrado'){
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="pt-br"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Proposta indisponível</title><script src="https://cdn.tailwindcss.com"></script></head><body class="bg-slate-100 min-h-screen flex items-center justify-center p-6"><div class="bg-white max-w-md w-full rounded-2xl shadow-lg p-8 text-center"><div class="text-5xl mb-4">⚠️</div><h1 class="text-xl font-bold text-slate-800 mb-2">Proposta indisponível</h1><p class="text-slate-600">'.e($mensagem).'</p></div></body></html>';
    exit;
}

if ($t === '' || !preg_match('/^[a-f0-9]{32,128}$/i', $t)) {
    pagina_indisponivel('Token inválido ou ausente.');
}

// ================= ORÇAMENTO PÚBLICO POR TOKEN =================
$stmt = $pdo->prepare("\n    SELECT \n        o.*,\n        e.nome AS empresa_nome,\n        e.logo AS empresa_logo,\n        e.whatsapp AS empresa_whatsapp,\n        e.status AS empresa_status\n    FROM orcamentos o\n    JOIN empresas e ON e.id = o.empresa_id\n    WHERE o.token = ?\n    AND o.deleted_at IS NULL\n    LIMIT 1\n");
$stmt->execute([$t]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$orc){
    pagina_indisponivel('Orçamento não encontrado.');
}

if (($orc['empresa_status'] ?? '') !== 'ativa') {
    pagina_indisponivel('Esta proposta está temporariamente indisponível.');
}

$empresa_id = (int)$orc['empresa_id'];

// ================= EMPRESA =================
$emp = [
    'logo' => $orc['empresa_logo'] ?? null,
    'nome' => $orc['empresa_nome'] ?? '',
    'whatsapp' => $orc['empresa_whatsapp'] ?? ''
];

$telefoneEmpresa = '';
if(!empty($emp['whatsapp'])){
    $digits = preg_replace('/\D+/', '', $emp['whatsapp']);
    if(strlen($digits) === 11){
        $telefoneEmpresa = preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digits);
    } elseif(strlen($digits) === 10){
        $telefoneEmpresa = preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digits);
    } else {
        $telefoneEmpresa = $emp['whatsapp'];
    }
}

// ================= MARCAR COMO VISUALIZADO =================
if ($orc['status'] === 'enviado') {
    $stmt = $pdo->prepare("\n        UPDATE orcamentos \n        SET status = 'visualizado' \n        WHERE id = ? \n        AND empresa_id = ? \n        AND status = 'enviado'\n        AND deleted_at IS NULL\n    ");
    $stmt->execute([$orc['id'], $empresa_id]);

    $orc['status'] = 'visualizado';
}

// ================= ITENS =================
$stmt = $pdo->prepare("\n    SELECT * \n    FROM orcamento_itens \n    WHERE orcamento_id = ? \n    AND empresa_id = ?\n    ORDER BY id ASC\n");
$stmt->execute([$orc['id'], $empresa_id]);
$itens = $stmt->fetchAll(PDO::FETCH_ASSOC);

$created  = !empty($orc['created_at']) ? date('d/m/Y', strtotime($orc['created_at'])) : '';
$validade = !empty($orc['validade'])   ? date('d/m/Y', strtotime($orc['validade']))   : '';
$status   = strtolower($orc['status']);

$classe = 'status-pendente';
if($status === 'aprovado'){ $classe = 'status-aprovado'; }
elseif($status === 'recusado'){ $classe = 'status-recusado'; }

$podeResponder = in_array($orc['status'], ['rascunho','enviado','visualizado'], true);

$baseUrl = base_url();
$urlPublica = $baseUrl . '/publico.php?t=' . urlencode($orc['token']);
$urlOgImage = $baseUrl . '/imagens/orcamentaria_express.png';
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Proposta Comercial</title>

<!-- Open Graph -->
<meta property="og:title" content="Proposta Comercial - Orçamentaria Express" />
<meta property="og:description" content="Clique para visualizar e aprovar sua proposta." />
<meta property="og:image" content="<?= e($urlOgImage) ?>" />
<meta property="og:url" content="<?= e($urlPublica) ?>" />
<meta property="og:type" content="website" />

<link rel="icon" type="image/png" href="<?= e($urlOgImage) ?>">

<meta property="og:image:secure_url" content="<?= e($urlOgImage) ?>" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="500" />
<meta property="og:image:height" content="420" />
<meta property="og:locale" content="pt_BR" />

<!-- WhatsApp / compat -->
<meta name="twitter:card" content="summary_large_image" />

<script src="https://cdn.tailwindcss.com"></script>

<style>
body { background:#f5f6f8; font-family: Arial, sans-serif; }
.container { max-width: 800px; margin: 30px auto; background:#fff; border-radius:12px; padding:25px; box-shadow:0 10px 30px rgba(0,0,0,0.08); }
.header { display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #eee; padding-bottom:15px; margin-bottom:20px; gap:16px; }
.btn { padding:12px 18px; border-radius:8px; color:#fff; font-weight:bold; cursor:pointer; border:0; transition:.15s; }
.btn:hover { filter: brightness(.95); transform: translateY(-1px); }
.btn:disabled { opacity:.65; cursor:not-allowed; transform:none; }
.btn-green { background:#28a745; }
.btn-red   { background:#dc3545; }
table { width:100%; border-collapse:collapse; }
th { background:#111; color:#fff; padding:10px; }
td { padding:10px; border-bottom:1px solid #eee; }
.total { text-align:right; font-size:20px; font-weight:bold; margin-top:20px; }
#toast { position: fixed; bottom: 20px; right: 20px; max-width: 320px; padding: 12px 16px; border-radius: 6px; color: #fff; font-size: 14px; background: #111; opacity: 0; transform: translateY(20px); transition: all .3s ease; z-index:9999; }
#toast.erro { background:#dc3545; }
#toast.sucesso { background:#28a745; }
.right {text-align:right;} .center {text-align:center;}
.status-aprovado { color: #28a745; }
.status-recusado { color: #dc3545; }
.status-pendente { color: #999; }
@media(max-width:640px){
    .container{margin:0;min-height:100vh;border-radius:0;padding:18px;}
    .header{align-items:flex-start;flex-direction:column;}
    table{font-size:13px;}
    th,td{padding:8px 6px;}
    .total{text-align:left;}
}
</style>
</head>

<body>
<div class="container">
    <div class="header">
        <div style="display:flex; align-items:center; gap:10px;">
            <img src="<?= e($urlOgImage) ?>" style="height:45px; display:block;" alt="Orçamentaria Express">
            
            <div style="display:flex; flex-direction:column;">
                <?php if(!empty($emp['logo'])): ?>
                    <img src="<?= e($emp['logo']) ?>" style="max-height:55px; max-width:180px; object-fit:contain;" alt="<?= e($emp['nome']) ?>">
                <?php else: ?>
                    <b><?= e($emp['nome']) ?></b>
                <?php endif; ?>
            
                <?php if(!empty($telefoneEmpresa)): ?>
                    <small style="color:#555;"><?= e($telefoneEmpresa) ?></small>
                <?php endif; ?>
            </div>
        </div>
        <div style="text-align:right;font-size:12px;">
            <div><b>Proposta:</b> #<?= e($orc['numero'] ?? $orc['id']) ?></div>
            <div><b>Data:</b> <?= e($created) ?></div>
            <div><b>Validade:</b> <?= e($validade) ?></div>
        </div>
    </div>

    <div style="margin-bottom:20px;">
        <b>Cliente:</b> <?= e($orc['cliente_nome']) ?><br>
        <b>WhatsApp:</b> <?= e($orc['cliente_whatsapp']) ?>
    </div>

    <table>
        <tr><th>Item</th><th>Qtd</th><th>Valor</th><th>Total</th></tr>
        <?php foreach($itens as $i): ?>
        <tr>
            <td><?= e($i['nome_snapshot']) ?></td>
            <td class="center"><?= e($i['quantidade']) ?></td>
            <td class="right">R$ <?= number_format((float)$i['preco_snapshot'],2,',','.') ?></td>
            <td class="right">R$ <?= number_format((float)$i['total'],2,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="total">
        Total: R$ <?= number_format((float)$orc['valor_total'],2,',','.') ?>
    </div>

    <div style="margin-top:20px;">
        Status: <b class="<?= e($classe) ?>"><?= strtoupper(e($orc['status'])) ?></b>
    </div>

    <?php if($podeResponder): ?>
    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;">
        <button id="btnAprovar" class="btn btn-green" onclick="acao('aprovar')">Aprovar</button>
        <button id="btnRecusar" class="btn btn-red" onclick="acao('recusar')">Recusar</button>
    </div>
    <?php endif; ?>
</div>

<div id="toast"></div>

<script>
window.toast = function(msg, tipo='ok'){
    const el = document.getElementById('toast');
    if(!el) return;
    el.classList.remove('erro','warn','ok','sucesso');
    el.classList.add(tipo);
    el.innerText = msg;
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
    setTimeout(()=>{ el.style.opacity = '0'; el.style.transform = 'translateY(20px)'; }, 2800);
};

function bloquearBotoes(bloquear){
    const a = document.getElementById('btnAprovar');
    const r = document.getElementById('btnRecusar');
    if(a) a.disabled = bloquear;
    if(r) r.disabled = bloquear;
}

function acao(tipo){
    const texto = tipo === 'aprovar' ? 'aprovar esta proposta' : 'recusar esta proposta';

    if(!confirm('Tem certeza que deseja '+texto+'?')){
        return;
    }

    bloquearBotoes(true);

    const body = new URLSearchParams();
    body.set('token', '<?= e($orc['token']) ?>');
    body.set('acao', tipo);

    fetch('/api/orcamento_publico_status.php',{
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body.toString()
    })
    .then(r=>r.json())
    .then(d=>{
        if(d.ok){ 
            toast('Proposta atualizada: '+(d.status || tipo), 'sucesso'); 
            setTimeout(()=>location.reload(), 700); 
        } else { 
            bloquearBotoes(false);
            toast(d.erro || 'Erro ao atualizar proposta', 'erro'); 
        }
    })
    .catch(()=>{
        bloquearBotoes(false);
        toast('Erro de conexão', 'erro');
    });
}
</script>
</body>
</html>
