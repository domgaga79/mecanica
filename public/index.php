<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/empresa.php';
require_once __DIR__ . '/../core/layout.php';

checkAuth();

$empresa_id = empresa_id();

if (!$empresa_id) {
    die("Empresa não identificada");
}

layout_header("Dashboard");

// ================= KPIs GERAIS =================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total,
        COALESCE(SUM(status = 'rascunho'), 0) AS rascunhos,
        COALESCE(SUM(status = 'enviado'), 0) AS enviados,
        COALESCE(SUM(status = 'visualizado'), 0) AS visualizados,
        COALESCE(SUM(status = 'aprovado'), 0) AS aprovados,
        COALESCE(SUM(status = 'recusado'), 0) AS recusados,
        COALESCE(SUM(valor_total), 0) AS total_proposto,
        COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END), 0) AS faturamento_aprovado
    FROM orcamentos
    WHERE empresa_id = ?
    AND deleted_at IS NULL
");
$stmt->execute([$empresa_id]);
$kpi = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$total = (int)($kpi['total'] ?? 0);
$rascunhos = (int)($kpi['rascunhos'] ?? 0);
$enviados = (int)($kpi['enviados'] ?? 0);
$visualizados = (int)($kpi['visualizados'] ?? 0);
$aprovados = (int)($kpi['aprovados'] ?? 0);
$recusados = (int)($kpi['recusados'] ?? 0);
$totalProposto = (float)($kpi['total_proposto'] ?? 0);
$faturamentoAprovado = (float)($kpi['faturamento_aprovado'] ?? 0);

// ================= KPIs DO MÊS ATUAL =================
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) AS total_mes,
        COALESCE(SUM(valor_total), 0) AS proposto_mes,
        COALESCE(SUM(CASE WHEN status = 'aprovado' THEN valor_total ELSE 0 END), 0) AS aprovado_mes
    FROM orcamentos
    WHERE empresa_id = ?
    AND deleted_at IS NULL
    AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    AND created_at < DATE_ADD(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL 1 MONTH)
");
$stmt->execute([$empresa_id]);
$mesAtual = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$totalMes = (int)($mesAtual['total_mes'] ?? 0);
$propostoMes = (float)($mesAtual['proposto_mes'] ?? 0);
$aprovadoMes = (float)($mesAtual['aprovado_mes'] ?? 0);

// ================= FATURAMENTO MENSAL APROVADO =================
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') AS mes,
        COALESCE(SUM(valor_total), 0) AS total
    FROM orcamentos
    WHERE empresa_id = ?
    AND deleted_at IS NULL
    AND status = 'aprovado'
    AND created_at >= DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 11 MONTH), '%Y-%m-01')
    GROUP BY mes
    ORDER BY mes ASC
");
$stmt->execute([$empresa_id]);
$faturamentoMensal = $stmt->fetchAll(PDO::FETCH_ASSOC);

$meses = [];
$valores = [];

foreach ($faturamentoMensal as $f) {
    $meses[] = $f['mes'];
    $valores[] = (float)$f['total'];
}

// ================= TICKET MÉDIO APROVADO =================
$stmt = $pdo->prepare("
    SELECT COALESCE(AVG(valor_total), 0)
    FROM orcamentos
    WHERE empresa_id = ?
    AND deleted_at IS NULL
    AND status = 'aprovado'
");
$stmt->execute([$empresa_id]);
$ticket = (float)$stmt->fetchColumn();

// ================= TOP CLIENTES APROVADOS =================
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(NULLIF(TRIM(cliente_nome), ''), 'Cliente sem nome') AS cliente_nome,
        COALESCE(SUM(valor_total), 0) AS total
    FROM orcamentos
    WHERE empresa_id = ?
    AND deleted_at IS NULL
    AND status = 'aprovado'
    GROUP BY COALESCE(NULLIF(TRIM(cliente_nome), ''), 'Cliente sem nome')
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([$empresa_id]);
$clientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ================= CONVERSÃO =================
$orcamentosConcluidos = $aprovados + $recusados;
$taxa = ($orcamentosConcluidos > 0)
    ? round(($aprovados / $orcamentosConcluidos) * 100, 1)
    : 0;

$taxaGeral = ($total > 0)
    ? round(($aprovados / $total) * 100, 1)
    : 0;
?>

<div class="flex items-center justify-between gap-3 mb-6">
    <h2 class="text-xl md:text-2xl font-bold">📈 Painel</h2>
    <a href="/index.php" onclick="return voltarCompat(event, '/index.php')" class="text-gray-500 hover:text-black font-semibold whitespace-nowrap">
        ← Voltar
    </a>
</div>

<!-- KPIs RESPONSIVOS -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">

    <div class="bg-white p-4 rounded-xl shadow text-center">
        <p class="text-gray-500 text-sm">Faturamento aprovado</p>
        <strong class="text-lg text-green-700">R$ <?= number_format($faturamentoAprovado, 2, ',', '.') ?></strong>
        <p class="text-xs text-gray-400 mt-1">Somente orçamentos aprovados</p>
    </div>

    <div class="bg-white p-4 rounded-xl shadow text-center">
        <p class="text-gray-500 text-sm">Total proposto</p>
        <strong class="text-lg">R$ <?= number_format($totalProposto, 2, ',', '.') ?></strong>
        <p class="text-xs text-gray-400 mt-1">Todos os status ativos</p>
    </div>

    <div class="bg-white p-4 rounded-xl shadow text-center">
        <p class="text-gray-500 text-sm">Orçamentos</p>
        <strong class="text-lg"><?= $total ?></strong>
        <p class="text-xs text-gray-400 mt-1"><?= $totalMes ?> neste mês</p>
    </div>

    <div class="bg-white p-4 rounded-xl shadow text-center">
        <p class="text-gray-500 text-sm">Conversão final</p>
        <strong class="text-lg"><?= $taxa ?>%</strong>
        <p class="text-xs text-gray-400 mt-1">Aprovados ÷ aprovados+recusados</p>
    </div>

</div>

<!-- KPIs DO MÊS -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-6">
    <div class="bg-white p-4 rounded-xl shadow">
        <p class="text-gray-500 text-sm">Proposto no mês</p>
        <strong class="text-xl">R$ <?= number_format($propostoMes, 2, ',', '.') ?></strong>
    </div>

    <div class="bg-white p-4 rounded-xl shadow">
        <p class="text-gray-500 text-sm">Aprovado no mês</p>
        <strong class="text-xl text-green-700">R$ <?= number_format($aprovadoMes, 2, ',', '.') ?></strong>
    </div>
</div>

<!-- FUNIL -->
<div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold mb-3">Funil de Orçamentos</h2>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-2 text-sm">
        <div class="text-center">📝 Rascunhos<br><b><?= $rascunhos ?></b></div>
        <div class="text-center">📤 Enviados<br><b><?= $enviados ?></b></div>
        <div class="text-center">👁️ Visualizados<br><b><?= $visualizados ?></b></div>
        <div class="text-center">✅ Aprovados<br><b><?= $aprovados ?></b></div>
        <div class="text-center">❌ Recusados<br><b><?= $recusados ?></b></div>
    </div>

    <div class="mt-4 text-xs text-gray-500 text-center">
        Conversão geral: <?= $taxaGeral ?>% considerando todos os orçamentos ativos.
    </div>
</div>

<!-- GRÁFICO FATURAMENTO -->
<div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold mb-1">Faturamento Mensal Aprovado</h2>
    <p class="text-xs text-gray-500 mb-3">Soma apenas orçamentos com status aprovado.</p>
    <?php if (empty($meses)): ?>
        <div class="text-center text-gray-500 text-sm py-8">Nenhum orçamento aprovado para exibir no gráfico.</div>
    <?php else: ?>
        <canvas id="lineChart"></canvas>
    <?php endif; ?>
</div>

<!-- GRÁFICO STATUS -->
<div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold mb-3">Distribuição de Status</h2>
    <?php if ($total <= 0): ?>
        <div class="text-center text-gray-500 text-sm py-8">Nenhum orçamento cadastrado.</div>
    <?php else: ?>
        <canvas id="donutChart"></canvas>
    <?php endif; ?>
</div>

<!-- TICKET MÉDIO -->
<div class="bg-white p-4 rounded-xl shadow mb-6 text-center">
    <p class="text-gray-500 text-sm">Ticket Médio Aprovado</p>
    <strong class="text-xl">R$ <?= number_format($ticket, 2, ',', '.') ?></strong>
</div>

<!-- TOP CLIENTES -->
<div class="bg-white p-4 rounded-xl shadow mb-6">
    <h2 class="font-bold mb-3">Top Clientes Aprovados</h2>

    <div class="space-y-2">
        <?php if (empty($clientes)): ?>
            <p class="text-center text-gray-500 text-sm py-4">Nenhum cliente aprovado ainda.</p>
        <?php endif; ?>

        <?php foreach ($clientes as $c): ?>
            <div class="flex justify-between border-b py-2 text-sm gap-3">
                <span><?= htmlspecialchars($c['cliente_nome'] ?? '', ENT_QUOTES, 'UTF-8') ?></span>
                <b class="whitespace-nowrap">R$ <?= number_format((float)$c['total'], 2, ',', '.') ?></b>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- AÇÕES -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">

    <a href="/novo_orcamento.php"
       class="bg-black text-white p-4 rounded-xl text-center font-semibold">
        ➕ Novo Orçamento
    </a>

    <a href="/pages/orcamentos.php"
       class="bg-white border p-4 rounded-xl text-center font-semibold">
        📄 Ver Orçamentos
    </a>

</div>

<!-- CHART.JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
<?php if (!empty($meses)): ?>
new Chart(document.getElementById('lineChart'), {
    type: 'line',
    data: {
        labels: <?= json_encode($meses, JSON_UNESCAPED_UNICODE) ?>,
        datasets: [{
            label: 'Faturamento aprovado',
            data: <?= json_encode($valores, JSON_UNESCAPED_UNICODE) ?>,
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.12)',
            fill: true,
            tension: 0.4
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: {
                display: true
            }
        },
        scales: {
            y: {
                beginAtZero: true
            }
        }
    }
});
<?php endif; ?>

<?php if ($total > 0): ?>
new Chart(document.getElementById('donutChart'), {
    type: 'doughnut',
    data: {
        labels: ['Rascunhos', 'Enviados', 'Visualizados', 'Aprovados', 'Recusados'],
        datasets: [{
            data: [
                <?= $rascunhos ?>,
                <?= $enviados ?>,
                <?= $visualizados ?>,
                <?= $aprovados ?>,
                <?= $recusados ?>
            ],
            backgroundColor: ['#9ca3af', '#3b82f6', '#f59e0b', '#10b981', '#ef4444']
        }]
    },
    options: {
        responsive: true
    }
});
<?php endif; ?>
</script>

<?php layout_footer(); ?>
