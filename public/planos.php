<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/layout.php';
require_once __DIR__ . '/../config/db.php';

checkAuth();
$empresa_id = empresa_id();

if (!$empresa_id) {
    die('Empresa não identificada');
}

// ================= DADOS DA EMPRESA / PLANO ATUAL =================
$stmt = $pdo->prepare("\n    SELECT \n        e.id,\n        e.nome,\n        e.whatsapp,\n        e.status,\n        e.plano_id,\n        COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos,\n        p.nome AS plano_nome,\n        p.limite_orcamentos,\n        p.limite_produtos,\n        p.preco AS plano_preco\n    FROM empresas e\n    LEFT JOIN planos p ON p.id = e.plano_id\n    WHERE e.id = :id\n    LIMIT 1\n");
$stmt->execute(['id' => $empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    die('Empresa não encontrada');
}

// ================= USO DO MÊS: ORÇAMENTOS =================
$stmt = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM orcamentos\n    WHERE empresa_id = ?\n      AND deleted_at IS NULL\n      AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')\n");
$stmt->execute([$empresa_id]);
$orcamentosMes = (int)$stmt->fetchColumn();

// ================= USO ATUAL: PRODUTOS ATIVOS =================
$stmt = $pdo->prepare("\n    SELECT COUNT(*)\n    FROM produtos\n    WHERE empresa_id = ?\n      AND deleted_at IS NULL\n");
$stmt->execute([$empresa_id]);
$produtosAtivos = (int)$stmt->fetchColumn();

// ================= PLANOS DISPONÍVEIS =================
$stmt = $pdo->query("SELECT * FROM planos WHERE ativo = 1 ORDER BY preco ASC, id ASC");
$planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

$limitePlanoOrcamentos = (int)($empresa['limite_orcamentos'] ?? 0);
$limiteExtraOrcamentos = max(0, (int)($empresa['limite_extra_orcamentos'] ?? 0));
$limiteTotalOrcamentos = $limitePlanoOrcamentos + $limiteExtraOrcamentos;
$limiteProdutos        = (int)($empresa['limite_produtos'] ?? 0);

$percentualOrcamentos = $limiteTotalOrcamentos > 0
    ? min(100, round(($orcamentosMes / $limiteTotalOrcamentos) * 100))
    : 0;

$percentualProdutos = $limiteProdutos > 0
    ? min(100, round(($produtosAtivos / $limiteProdutos) * 100))
    : 0;

function moneyPlano($valor) {
    return ((float)$valor <= 0) ? 'Grátis' : 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

layout_header('Planos');
?>

<div class="max-w-5xl mx-auto">
    <div class="flex items-center justify-between mb-6">
        <div>
            <h2 class="text-xl md:text-2xl font-bold">💎 Planos</h2>
            <p class="text-sm text-gray-500 mt-1">
                Cliente selecionado: <strong><?= h($empresa['nome'] ?? '') ?></strong>
            </p>
        </div>
        <a href="/index.php" onclick="return voltarCompat(event, '/index.php')" 
            class="text-gray-500 hover:text-black font-semibold whitespace-nowrap">
            &larr; Voltar
        </a>
    </div>

    <!-- RESUMO DO PLANO ATUAL -->
    <div class="bg-white rounded-xl shadow-md p-5 mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-5">
            <div>
                <p class="text-sm text-gray-500">Plano atual</p>
                <h3 class="text-2xl font-bold">
                    <?= h($empresa['plano_nome'] ?? '-') ?>
                </h3>
                <p class="text-sm text-gray-500 mt-1">
                    Status da empresa: <strong><?= h($empresa['status'] ?? '-') ?></strong>
                </p>
            </div>
            <div class="text-left md:text-right">
                <p class="text-sm text-gray-500">Valor do plano</p>
                <strong class="text-xl"><?= moneyPlano($empresa['plano_preco'] ?? 0) ?></strong>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="border rounded-xl p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="text-sm text-gray-500">Orçamentos usados no mês</p>
                        <strong class="text-xl">
                            <?= $orcamentosMes ?> / <?= $limiteTotalOrcamentos ?>
                        </strong>
                    </div>
                    <span class="text-xs font-bold px-2 py-1 rounded bg-blue-100 text-blue-700">
                        <?= $percentualOrcamentos ?>%
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                    <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentualOrcamentos ?>%"></div>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs text-gray-600">
                    <div class="bg-gray-50 rounded-lg p-2">
                        Limite do plano<br>
                        <strong class="text-gray-900"><?= $limitePlanoOrcamentos ?></strong>
                    </div>
                    <div class="bg-gray-50 rounded-lg p-2">
                        Limite extra<br>
                        <strong class="text-gray-900"><?= $limiteExtraOrcamentos ?></strong>
                    </div>
                </div>
            </div>

            <div class="border rounded-xl p-4">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <p class="text-sm text-gray-500">Produtos ativos</p>
                        <strong class="text-xl">
                            <?= $produtosAtivos ?> / <?= $limiteProdutos ?>
                        </strong>
                    </div>
                    <span class="text-xs font-bold px-2 py-1 rounded bg-green-100 text-green-700">
                        <?= $percentualProdutos ?>%
                    </span>
                </div>

                <div class="w-full bg-gray-200 rounded-full h-2 mb-3">
                    <div class="bg-green-600 h-2 rounded-full" style="width: <?= $percentualProdutos ?>%"></div>
                </div>

                <p class="text-xs text-gray-500">
                    Produtos com exclusão lógica não entram nessa contagem.
                </p>
            </div>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <h3 class="text-lg md:text-xl font-bold">Escolha seu Plano</h3>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <?php foreach($planos as $p): ?>
            <?php
                $isAtual = ((int)$p['id'] === (int)$empresa['plano_id']);
                $orcamentosComExtra = (int)$p['limite_orcamentos'] + $limiteExtraOrcamentos;
                $precoPlano = (float)$p['preco'];
                $podeMudarDireto = ($precoPlano <= 0);
                $bloqueiaPorOrcamentos = ($orcamentosComExtra > 0 && $orcamentosMes > $orcamentosComExtra);
                $bloqueiaPorProdutos = ((int)$p['limite_produtos'] > 0 && $produtosAtivos > (int)$p['limite_produtos']);
                $bloqueadoPorUso = $bloqueiaPorOrcamentos || $bloqueiaPorProdutos;
            ?>

            <div class="bg-white p-6 rounded-xl shadow-md flex flex-col justify-between <?= $isAtual ? 'ring-2 ring-green-500' : '' ?>">
                <div>
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <h3 class="text-lg font-bold"><?= h($p['nome']) ?></h3>
                        <?php if($isAtual): ?>
                            <span class="text-xs font-bold bg-green-100 text-green-700 px-2 py-1 rounded-full">
                                Plano atual
                            </span>
                        <?php endif; ?>
                    </div>

                    <p class="text-gray-600 mb-2">
                        Limite de orçamentos do plano: 
                        <strong><?= (int)$p['limite_orcamentos'] ?></strong>
                    </p>

                    <p class="text-gray-600 mb-2">
                        Limite extra da empresa: 
                        <strong><?= $limiteExtraOrcamentos ?></strong>
                    </p>

                    <p class="text-gray-600 mb-2">
                        Total de orçamentos com extra: 
                        <strong><?= $orcamentosComExtra ?></strong>
                    </p>

                    <p class="text-gray-600 mb-2">
                        Limite de produtos: 
                        <strong><?= (int)$p['limite_produtos'] ?></strong>
                    </p>

                    <p class="text-gray-800 text-xl font-semibold mt-3">
                        <?= moneyPlano($p['preco']) ?>
                    </p>

                    <?php if(!$isAtual && $bloqueadoPorUso && $podeMudarDireto): ?>
                        <div class="mt-3 text-xs bg-yellow-50 text-yellow-800 border border-yellow-200 rounded-lg p-3">
                            Este plano não pode ser ativado agora porque o uso atual excede um dos limites.
                        </div>
                    <?php endif; ?>
                </div>

                <?php if($isAtual): ?>
                    <button 
                        type="button"
                        disabled
                        class="mt-4 w-full bg-gray-200 text-gray-600 font-semibold py-3 rounded-lg cursor-not-allowed">
                        Plano atual
                    </button>
                <?php elseif($podeMudarDireto): ?>
                    <button 
                        type="button"
                        data-plano-id="<?= (int)$p['id'] ?>"
                        data-plano-nome="<?= h($p['nome']) ?>"
                        <?= $bloqueadoPorUso ? 'disabled' : '' ?>
                        class="js-selecionar-plano mt-4 w-full <?= $bloqueadoPorUso ? 'bg-gray-300 text-gray-600 cursor-not-allowed' : 'bg-green-600 text-white hover:bg-green-800' ?> font-semibold py-3 rounded-lg shadow transition">
                        Usar Plano <?= h($p['nome']) ?>
                    </button>
                <?php else: ?>
                    <button 
                        type="button"
                        data-plano-id="<?= (int)$p['id'] ?>"
                        data-plano-nome="<?= h($p['nome']) ?>"
                        class="js-assinar-plano mt-4 w-full bg-green-600 text-white font-semibold py-3 rounded-lg shadow hover:bg-green-800 transition">
                        Assinar Plano <?= h($p['nome']) ?>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function planosToast(msg, tipo){
    if (typeof window.toast === 'function') {
        window.toast(msg || '', tipo || 'info');
        return;
    }
    alert(msg || '');
}

async function selecionarPlano(id, nome){
    if(!confirm('Deseja alterar para o Plano ' + nome + '?')){
        return;
    }

    const form = new FormData();
    form.append('plano_id', id);

    try {
        const res = await fetch('/api/selecionar_plano.php', {
            method: 'POST',
            body: form,
            credentials: 'same-origin'
        });

        const data = await res.json().catch(function(){ return null; });

        if(data && data.ok){
            planosToast(data.mensagem || 'Plano atualizado com sucesso.', 'sucesso');
            setTimeout(function(){ window.location.reload(); }, 900);
            return;
        }

        planosToast((data && data.erro) ? data.erro : 'Erro ao selecionar plano.', 'erro');
    } catch (e) {
        planosToast('Falha de conexão ao selecionar plano.', 'erro');
    }
}

const empresaNome = <?= json_encode($empresa['nome'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
const empresaWhatsapp = <?= json_encode($empresa['whatsapp'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
const planoAtual = <?= json_encode($empresa['plano_nome'] ?? '', JSON_UNESCAPED_UNICODE) ?>;
const orcamentosUsados = <?= (int)$orcamentosMes ?>;
const orcamentosLimiteTotal = <?= (int)$limiteTotalOrcamentos ?>;
const produtosUsados = <?= (int)$produtosAtivos ?>;
const produtosLimite = <?= (int)$limiteProdutos ?>;

function assinarPlano(id, nome){
    const numero = '5571993143374';
    const linhas = [
        'Olá, quero assinar o Plano ' + nome + ' (ID ' + id + ').',
        'Empresa: ' + empresaNome,
        'WhatsApp: ' + empresaWhatsapp,
        'Plano atual: ' + planoAtual,
        'Orçamentos usados no mês: ' + orcamentosUsados + '/' + orcamentosLimiteTotal,
        'Produtos ativos: ' + produtosUsados + '/' + produtosLimite
    ];

    const url = 'https://wa.me/' + numero + '?text=' + encodeURIComponent(linhas.join('\n'));
    window.open(url, '_blank');
}

document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.js-selecionar-plano').forEach(function(btn){
        btn.addEventListener('click', function(){
            selecionarPlano(btn.dataset.planoId, btn.dataset.planoNome || '');
        });
    });

    document.querySelectorAll('.js-assinar-plano').forEach(function(btn){
        btn.addEventListener('click', function(){
            assinarPlano(btn.dataset.planoId, btn.dataset.planoNome || '');
        });
    });
});
</script>

<?php layout_footer(); ?>
