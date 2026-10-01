<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/layout.php';
require_once __DIR__ . '/../config/db.php';

checkAuth();
$empresa_id = empresa_id();

if (!$empresa_id) {
    die('Empresa não identificada');
}

// ================= DADOS DA EMPRESA / PLANO =================
$stmt = $pdo->prepare("
    SELECT 
        e.id,
        e.nome,
        e.slug,
        e.whatsapp,
        e.status,
        e.created_at,
        e.logo,
        e.assinatura,
        e.plano_id,
        COALESCE(e.limite_extra_orcamentos, 0) AS limite_extra_orcamentos,
        p.nome AS plano_nome,
        p.limite_orcamentos,
        p.limite_produtos,
        p.preco AS plano_preco
    FROM empresas e
    LEFT JOIN planos p ON p.id = e.plano_id
    WHERE e.id = :id
    LIMIT 1
");
$stmt->execute(['id' => $empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    die('Empresa não encontrada');
}

// ================= USO DO MÊS: ORÇAMENTOS =================
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM orcamentos
    WHERE empresa_id = ?
      AND deleted_at IS NULL
      AND created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
");
$stmt->execute([$empresa_id]);
$orcamentosMes = (int)$stmt->fetchColumn();

// ================= USO ATUAL: PRODUTOS ATIVOS =================
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM produtos
    WHERE empresa_id = ?
      AND deleted_at IS NULL
");
$stmt->execute([$empresa_id]);
$produtosAtivos = (int)$stmt->fetchColumn();

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

function moneyPerfil($valor) {
    return ((float)$valor <= 0) ? 'Grátis' : 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

layout_header("Perfil da Empresa");
?>

<div class="w-full">
    <div class="flex items-center justify-between mb-6">
        <h2 class="text-xl md:text-2xl font-bold">🏢 Perfil da Empresa</h2>
        <a href="/index.php" onclick="return voltarCompat(event, '/index.php')" 
            class="text-gray-500 hover:text-black font-semibold whitespace-nowrap">
            &larr; Voltar
        </a>
    </div>

    <div class="bg-white p-6 rounded-xl shadow-md space-y-4 mb-6">
        <?php if(!empty($empresa['logo'])): ?>
            <div class="flex justify-center mb-4">
                <img src="<?= htmlspecialchars($empresa['logo']) ?>" 
                     alt="Logo da empresa" 
                     class="h-24 object-contain">
            </div>
        <?php endif; ?>

        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">Nome</span>
            <strong class="text-right"><?= htmlspecialchars($empresa['nome'] ?? '') ?></strong>
        </div>
        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">Slug</span>
            <strong class="text-right"><?= htmlspecialchars($empresa['slug'] ?? '') ?></strong>
        </div>
        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">WhatsApp</span>
            <strong class="text-right"><?= htmlspecialchars($empresa['whatsapp'] ?? '') ?></strong>
        </div>
        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">Status</span>
            <strong class="text-right"><?= htmlspecialchars($empresa['status'] ?? '') ?></strong>
        </div>
        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">Plano atual</span>
            <strong class="text-right"><?= htmlspecialchars($empresa['plano_nome'] ?? ($empresa['assinatura'] ?? '-')) ?></strong>
        </div>
        <div class="flex justify-between border-b pb-2 gap-4">
            <span class="text-gray-600">Valor do plano</span>
            <strong class="text-right"><?= moneyPerfil($empresa['plano_preco'] ?? 0) ?></strong>
        </div>
        <div class="flex justify-between gap-4">
            <span class="text-gray-600">Data de Cadastro</span>
            <strong class="text-right"><?= $empresa['created_at'] ? date('d/m/Y H:i', strtotime($empresa['created_at'])) : '-' ?></strong>
        </div>
    </div>

    <!-- USO DO PLANO -->
    <div class="bg-white p-6 rounded-xl shadow-md mb-6">
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-2 mb-5">
            <div>
                <h3 class="text-lg font-bold">📊 Uso do Plano</h3>
                <p class="text-sm text-gray-500">
                    Cliente selecionado: <strong><?= htmlspecialchars($empresa['nome'] ?? '') ?></strong>
                </p>
            </div>
            <a href="/planos.php" class="text-sm bg-black text-white px-4 py-2 rounded-lg text-center font-semibold">
                Ver Planos
            </a>
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
                    Produtos ativos são produtos sem exclusão lógica.
                </p>
            </div>
        </div>
    </div>
</div>

<?php layout_footer(); ?>
