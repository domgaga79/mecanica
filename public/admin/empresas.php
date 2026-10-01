<?php
require_once __DIR__ . '/../../core/auth.php';
require_once __DIR__ . '/../../core/layout.php';
require_once __DIR__ . '/../../config/db.php';

checkAuth();

function painelSuperAdmin(PDO $pdo): bool
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

    // Restrição prática para o banco atual: somente a empresa-mãe Bacuri Digital gerencia planos de clientes.
    return $slug === 'bacuri-digital';
}

if (!painelSuperAdmin($pdo)) {
    auth_response_forbidden('Esta área é restrita ao administrador da Bacuri Digital.');
}

$planosStmt = $pdo->query("SELECT id, nome, limite_orcamentos, limite_produtos, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC, id ASC");
$planos = $planosStmt->fetchAll(PDO::FETCH_ASSOC);

$empresasStmt = $pdo->query("
    SELECT
        e.id,
        e.nome,
        e.slug,
        e.whatsapp,
        e.status,
        e.created_at,
        e.plano_id,
        e.assinatura,
        COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos,
        p.nome AS plano_nome,
        COALESCE(p.limite_orcamentos, 0) AS limite_orcamentos,
        COALESCE(p.limite_produtos, 0) AS limite_produtos,
        COALESCE(p.preco, 0) AS preco,
        (
            SELECT COUNT(*)
            FROM orcamentos o
            WHERE o.empresa_id = e.id
              AND o.deleted_at IS NULL
              AND o.created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
        ) AS orcamentos_mes,
        (
            SELECT COUNT(*)
            FROM produtos pr
            WHERE pr.empresa_id = e.id
              AND pr.deleted_at IS NULL
        ) AS produtos_ativos
    FROM empresas e
    LEFT JOIN planos p ON p.id = e.plano_id
    ORDER BY e.created_at DESC, e.id DESC
");
$empresas = $empresasStmt->fetchAll(PDO::FETCH_ASSOC);

$empresaSelecionadaId = isset($_GET['empresa_id']) ? (int)$_GET['empresa_id'] : 0;
$empresaSelecionada = null;

foreach ($empresas as $empresa) {
    if ($empresaSelecionadaId > 0 && (int)$empresa['id'] === $empresaSelecionadaId) {
        $empresaSelecionada = $empresa;
        break;
    }
}

if (!$empresaSelecionada && !empty($empresas)) {
    $empresaSelecionada = $empresas[0];
    $empresaSelecionadaId = (int)$empresaSelecionada['id'];
}

$limitePlano = (int)($empresaSelecionada['limite_orcamentos'] ?? 0);
$limiteExtra = (int)($empresaSelecionada['limite_extra_orcamentos'] ?? 0);
$limiteTotal = $limitePlano + $limiteExtra;
$orcamentosMes = (int)($empresaSelecionada['orcamentos_mes'] ?? 0);
$produtosAtivos = (int)($empresaSelecionada['produtos_ativos'] ?? 0);
$limiteProdutos = (int)($empresaSelecionada['limite_produtos'] ?? 0);

layout_header('Administração de Empresas');
?>

<div class="max-w-6xl mx-auto space-y-6">

    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h1 class="text-xl md:text-2xl font-bold leading-tight">⚙️ Admin de Empresas</h1>
            <p class="text-sm text-gray-500">Altere plano, status e limite extra de orçamentos dos clientes.</p>
        </div>

        <a
            href="/index.php"
            onclick="return voltarCompat(event, '/index.php')"
            class="shrink-0 text-gray-500 hover:text-black font-semibold whitespace-nowrap text-base md:text-base pt-1"
        >
            &larr; Voltar
        </a>
    </div>

    <div class="bg-white rounded-xl shadow p-4 text-center">
        <label class="block text-sm font-semibold text-gray-600 mb-2">Cliente selecionado</label>
        <select id="empresaSelect" class="w-full border rounded-lg p-3" onchange="trocarEmpresa(this.value)">
            <?php foreach ($empresas as $empresa): ?>
                <option value="<?= (int)$empresa['id'] ?>" <?= ((int)$empresa['id'] === $empresaSelecionadaId) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($empresa['nome'] ?? '') ?>
                    <?= !empty($empresa['plano_nome']) ? ' • ' . htmlspecialchars($empresa['plano_nome']) : '' ?>
                    <?= !empty($empresa['status']) ? ' • ' . htmlspecialchars($empresa['status']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <?php if (!$empresaSelecionada): ?>
        <div class="bg-white rounded-xl shadow p-6">
            <p class="text-gray-600">Nenhuma empresa encontrada.</p>
        </div>
    <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
            <div class="bg-white rounded-xl shadow p-4 text-center">
                <p class="text-gray-500 text-sm">Plano atual</p>
                <strong class="text-lg"><?= htmlspecialchars($empresaSelecionada['plano_nome'] ?? $empresaSelecionada['assinatura'] ?? '-') ?></strong>
            </div>

            <div class="bg-white rounded-xl shadow p-4 text-center">
                <p class="text-gray-500 text-sm">Orçamentos no mês</p>
                <strong class="text-lg"><?= $orcamentosMes ?> / <?= $limiteTotal ?></strong>
                <p class="text-xs text-gray-500 mt-1">Plano <?= $limitePlano ?> + Extra <?= $limiteExtra ?></p>
            </div>

            <div class="bg-white rounded-xl shadow p-4 text-center">
                <p class="text-gray-500 text-sm">Produtos ativos</p>
                <strong class="text-lg"><?= $produtosAtivos ?> / <?= $limiteProdutos ?></strong>
            </div>

            <div class="bg-white rounded-xl shadow p-4 text-center">
                <p class="text-gray-500 text-sm">Status</p>
                <strong class="text-lg <?= ($empresaSelecionada['status'] ?? '') === 'ativa' ? 'text-green-600' : 'text-red-600' ?>">
                    <?= htmlspecialchars($empresaSelecionada['status'] ?? '-') ?>
                </strong>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <form id="formEmpresa" class="lg:col-span-2 bg-white rounded-xl shadow p-6 space-y-4">
                <input type="hidden" name="empresa_id" value="<?= (int)$empresaSelecionada['id'] ?>">

                <div>
                    <h2 class="text-lg font-bold mb-1">Editar cliente</h2>
                    <p class="text-sm text-gray-500">
                        <?= htmlspecialchars($empresaSelecionada['nome'] ?? '') ?>
                        <?php if (!empty($empresaSelecionada['slug'])): ?>
                            <span class="text-gray-400">/ <?= htmlspecialchars($empresaSelecionada['slug']) ?></span>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Plano</label>
                        <select name="plano_id" class="w-full border rounded-lg p-3" required>
                            <?php foreach ($planos as $plano): ?>
                                <option value="<?= (int)$plano['id'] ?>" <?= ((int)$empresaSelecionada['plano_id'] === (int)$plano['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plano['nome']) ?> - <?= (int)$plano['limite_orcamentos'] ?> orçam. / <?= (int)$plano['limite_produtos'] ?> prod
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-semibold text-gray-600 mb-1">Status</label>
                        <select name="status" class="w-full border rounded-lg p-3" required>
                            <option value="ativa" <?= ($empresaSelecionada['status'] ?? '') === 'ativa' ? 'selected' : '' ?>>Ativa</option>
                            <option value="suspensa" <?= ($empresaSelecionada['status'] ?? '') === 'suspensa' ? 'selected' : '' ?>>Suspensa</option>
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-600 mb-1">Limite extra de orçamentos</label>
                    <input
                        type="number"
                        name="limite_extra_orcamentos"
                        value="<?= $limiteExtra ?>"
                        min="0"
                        step="1"
                        class="w-full border rounded-lg p-3"
                        style="text-align:end"
                        required
                    >
                    <p class="text-xs text-gray-500 mt-1">
                        Esse valor soma ao limite mensal do plano. Ex.: FREE 10 + extra 5 = 15 orçamentos/mês.
                    </p>
                </div>

                <div class="flex flex-col md:flex-row gap-3">
                    <button type="submit" id="btnSalvar" class="bg-black text-white px-5 py-3 rounded-lg font-semibold hover:bg-gray-800">
                        Salvar alterações
                    </button>
                    <a href="/perfil.php" class="bg-gray-100 text-gray-700 px-5 py-3 rounded-lg font-semibold text-center hover:bg-gray-200">
                        Ver meu perfil
                    </a>
                </div>
            </form>

            <div class="bg-white rounded-xl shadow p-6 space-y-3">
                <h2 class="text-lg font-bold">Resumo do cliente</h2>

                <div class="border-b pb-2">
                    <p class="text-gray-500 text-sm">Nome</p>
                    <strong><?= htmlspecialchars($empresaSelecionada['nome'] ?? '') ?></strong>
                </div>

                <div class="border-b pb-2">
                    <p class="text-gray-500 text-sm">WhatsApp</p>
                    <strong><?= htmlspecialchars($empresaSelecionada['whatsapp'] ?? '-') ?></strong>
                </div>

                <div class="border-b pb-2">
                    <p class="text-gray-500 text-sm">Cadastro</p>
                    <strong><?= !empty($empresaSelecionada['created_at']) ? date('d/m/Y H:i', strtotime($empresaSelecionada['created_at'])) : '-' ?></strong>
                </div>

                <div class="border-b pb-2">
                    <p class="text-gray-500 text-sm">Valor do plano</p>
                    <strong>
                        <?= ((float)$empresaSelecionada['preco'] <= 0) ? 'Grátis' : 'R$ ' . number_format((float)$empresaSelecionada['preco'], 2, ',', '.') ?>
                    </strong>
                </div>

                <div>
                    <p class="text-gray-500 text-sm">Limite total de orçamentos</p>
                    <strong><?= $limiteTotal ?></strong>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<script>
// Voltar compatível com desktop/mobile:
// - volta pelo histórico apenas quando veio de outra página interna;
// - evita voltar entre trocas de empresa_id da própria tela;
// - se abriu direto, cai no destino seguro informado.
window.voltarCompat = window.voltarCompat || function(event, fallbackUrl){
    if(event && typeof event.preventDefault === 'function'){
        event.preventDefault();
    }

    const destinoSeguro = fallbackUrl || '/index.php';

    try {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        const origemAtual = window.location.origin;
        const caminhoAtual = window.location.pathname;

        if(
            referrer &&
            referrer.origin === origemAtual &&
            referrer.pathname !== caminhoAtual &&
            window.history.length > 1
        ){
            window.history.back();
            return false;
        }
    } catch (e) {}

    window.location.href = destinoSeguro;
    return false;
};

function trocarEmpresa(id){
    window.location.href = '/admin/empresas.php?empresa_id=' + encodeURIComponent(id);
}

const formEmpresa = document.getElementById('formEmpresa');
const btnSalvar = document.getElementById('btnSalvar');

if(formEmpresa){
    formEmpresa.addEventListener('submit', async function(e){
        e.preventDefault();

        const textoOriginal = btnSalvar.innerText;
        btnSalvar.disabled = true;
        btnSalvar.innerText = 'Salvando...';

        try {
            const form = new FormData(formEmpresa);
            const res = await fetch('/api/admin_empresa_salvar.php', {
                method: 'POST',
                body: form
            });

            const data = await res.json();

            if(data.ok){
                toast(data.mensagem || 'Empresa atualizada com sucesso', 'sucesso');
                setTimeout(() => window.location.reload(), 700);
            } else {
                toast(data.erro || 'Erro ao salvar empresa', 'erro');
            }
        } catch (err) {
            toast('Falha de conexão ao salvar empresa', 'erro');
        } finally {
            btnSalvar.disabled = false;
            btnSalvar.innerText = textoOriginal;
        }
    });
}
</script>

<?php layout_footer(); ?>
