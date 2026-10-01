<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../core/layout.php';

checkAuth();

$empresa_id = (int)empresa_id();
$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('Orçamento inválido');
}

// ================= ORÇAMENTO =================
$stmt = $pdo->prepare("\n    SELECT *\n    FROM orcamentos\n    WHERE id = ?\n    AND empresa_id = ?\n    AND deleted_at IS NULL\n    LIMIT 1\n");
$stmt->execute([$id, $empresa_id]);
$orc = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$orc) {
    http_response_code(404);
    die('Orçamento não encontrado');
}

$numero_orcamento = (int)($orc['numero'] ?? $id);
$finalizado = in_array($orc['status'], ['aprovado', 'recusado'], true);

// ================= ITENS =================
$stmt = $pdo->prepare("\n    SELECT\n        i.id,
        i.nome_snapshot,
        i.preco_snapshot,
        i.quantidade,
        i.total
    FROM orcamento_itens i\n    JOIN orcamentos o ON o.id = i.orcamento_id\n    WHERE i.orcamento_id = ?\n    AND i.empresa_id = ?\n    AND o.empresa_id = ?\n    AND o.deleted_at IS NULL\n    ORDER BY i.id ASC\n");
$stmt->execute([$id, $empresa_id, $empresa_id]);
$itensBanco = $stmt->fetchAll(PDO::FETCH_ASSOC);

$itensJs = array_map(static function ($item) {
    return [
        'nome'  => (string)($item['nome_snapshot'] ?? ''),
        'preco' => (float)($item['preco_snapshot'] ?? 0),
        'qtd'   => (int)($item['quantidade'] ?? 1),
    ];
}, $itensBanco);

$statusLabels = [
    'rascunho'     => 'Rascunho',
    'enviado'      => 'Enviado',
    'visualizado'  => 'Visualizado',
    'aprovado'     => 'Aprovado',
    'recusado'     => 'Recusado',
];

$statusClasses = [
    'rascunho'     => 'bg-gray-100 text-gray-700',
    'enviado'      => 'bg-blue-100 text-blue-700',
    'visualizado'  => 'bg-purple-100 text-purple-700',
    'aprovado'     => 'bg-green-100 text-green-700',
    'recusado'     => 'bg-red-100 text-red-700',
];

$status = (string)($orc['status'] ?? 'rascunho');
$statusLabel = $statusLabels[$status] ?? ucfirst($status);
$statusClass = $statusClasses[$status] ?? 'bg-gray-100 text-gray-700';

layout_header('Editar Orçamento');
?>

<style>
body{ background:#f1f5f9; }
.input-erro{ border:2px solid #dc3545 !important; background:#fff5f5; }
.campo-bloqueado{ background:#f8fafc; color:#64748b; }
@media(max-width:768px){ .wrap-page{ max-width:100%; padding:12px; } }
</style>

<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
    <div>
        <h2 class="text-xl md:text-2xl font-bold">
            Editar Orçamento #<?= (int)$numero_orcamento ?>
        </h2>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-gray-600">
            <span class="px-3 py-1 rounded-full font-semibold <?= htmlspecialchars($statusClass) ?>">
                <?= htmlspecialchars($statusLabel) ?>
            </span>
            <span>
                Cliente: <strong><?= htmlspecialchars($orc['cliente_nome'] ?? '-') ?></strong>
            </span>
            <span class="w-full text-right">
                Total atual: <strong>R$ <?= number_format((float)($orc['valor_total'] ?? 0), 2, ',', '.') ?></strong>
            </span>
        </div>
    </div>

    <a href="/pages/orcamentos.php" onclick="return voltarCompat(event, '/pages/orcamentos.php')"
       class="text-gray-500 hover:text-black font-semibold text-left md:text-right whitespace-nowrap">
        &larr; Voltar para orçamentos
    </a>
</div>

<?php if ($finalizado): ?>
    <div class="mb-4 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl p-4">
        Este orçamento já está <strong><?= htmlspecialchars($statusLabel) ?></strong> e não pode ser editado.
        Os campos abaixo ficam disponíveis apenas para conferência.
    </div>
<?php endif; ?>

<div class="bg-white p-4 md:p-6 rounded-xl shadow space-y-4">

    <!-- cliente -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
        <input id="cliente"
            value="<?= htmlspecialchars($orc['cliente_nome'] ?? '') ?>"
            placeholder="Nome do cliente"
            <?= $finalizado ? 'readonly' : '' ?>
            class="w-full border rounded-lg px-3 py-3 <?= $finalizado ? 'campo-bloqueado' : '' ?>">

        <input id="whatsapp"
            value="<?= htmlspecialchars($orc['cliente_whatsapp'] ?? '') ?>"
            placeholder="WhatsApp"
            <?= $finalizado ? 'readonly' : '' ?>
            class="w-full border rounded-lg px-3 py-3 <?= $finalizado ? 'campo-bloqueado' : '' ?>">
    </div>

    <?php if (!$finalizado): ?>
        <!-- busca -->
        <div class="relative">
            <input id="busca"
                placeholder="Buscar produto ou serviço"
                autocomplete="off"
                class="w-full border rounded-lg px-3 py-3">

            <div id="resultados"
                class="absolute z-10 w-full bg-white border rounded-lg mt-1 shadow hidden max-h-72 overflow-y-auto"></div>
        </div>
    <?php endif; ?>

    <!-- itens -->
    <div>
        <h3 class="font-semibold mb-2">Itens</h3>
        <div id="lista" class="space-y-3"></div>
    </div>

    <!-- total -->
    <div class="flex justify-between text-lg font-bold border-t pt-3">
        <span>Total</span>
        <span id="totalGeral">R$ 0,00</span>
    </div>

    <?php if (!$finalizado): ?>
        <!-- botões -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
            <button onclick="addItem()"
                class="w-full bg-gray-500 text-white font-semibold py-4 rounded-xl shadow-md transition-colors duration-300 hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2">
                + Adicionar Item Manual
            </button>

            <button id="btnSalvar"
                onclick="salvar()"
                class="w-full bg-green-600 text-white font-semibold py-4 rounded-xl shadow-md transition-colors duration-300 hover:bg-green-800 focus:outline-none focus:ring-2 focus:ring-green-400 focus:ring-offset-2">
                Salvar Alterações
            </button>
        </div>
    <?php else: ?>
        <div class="pt-2">
            <button onclick="location.href='/pages/orcamentos.php'"
                class="w-full bg-gray-600 text-white font-semibold py-4 rounded-xl shadow-md hover:bg-gray-700">
                Voltar para a listagem
            </button>
        </div>
    <?php endif; ?>

</div>

<script>
// ================= BASE =================
const id = <?= (int)$id ?>;
const finalizado = <?= $finalizado ? 'true' : 'false' ?>;
const lista = document.getElementById('lista');
const clienteInput = document.getElementById('cliente');
const whatsappInput = document.getElementById('whatsapp');
const btnSalvar = document.getElementById('btnSalvar');

let itens = <?= json_encode($itensJs, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

// ================= BUSCA =================
const busca = document.getElementById('busca');
const resultados = document.getElementById('resultados');
let buscaTimer = null;

if (busca) {
    busca.addEventListener('input', () => {
        clearTimeout(buscaTimer);
        buscaTimer = setTimeout(buscar, 250);
    });

    document.addEventListener('click', (ev) => {
        if (!resultados || !busca) return;
        if (!resultados.contains(ev.target) && ev.target !== busca) {
            fecharResultados();
        }
    });
}

async function buscar(){
    if (!busca || !resultados) return;

    const q = busca.value.trim();

    if(q.length < 2){
        fecharResultados();
        return;
    }

    resultados.innerHTML = '<div class="p-3 text-gray-500">Buscando...</div>';
    resultados.classList.remove('hidden');

    try {
        const res = await fetch('/api/produtos.php?q=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        const data = await res.json();

        if (!res.ok || data.ok === false) {
            throw new Error(data.erro || 'Erro ao buscar produtos');
        }

        const produtos = Array.isArray(data) ? data : (data.produtos || []);
        renderResultados(produtos, q);

    } catch (err) {
        resultados.innerHTML = `<div class="p-3 text-red-600">${escapeHtml(err.message || 'Erro ao buscar produtos')}</div>`;
        resultados.classList.remove('hidden');
    }
}

function renderResultados(produtos, q) {
    resultados.innerHTML = '';

    produtos.forEach(p => {
        const item = document.createElement('button');
        item.type = 'button';
        item.className = 'w-full text-left p-3 hover:bg-gray-100 cursor-pointer border-b';
        item.innerHTML = `<strong>${escapeHtml(p.nome || '')}</strong><br><span class="text-sm text-gray-500">R$ ${formatarReal(p.preco || 0)}</span>`;
        item.onclick = () => selecionarProduto(String(p.nome || ''), Number(p.preco || 0));
        resultados.appendChild(item);
    });

    const criar = document.createElement('button');
    criar.type = 'button';
    criar.className = 'w-full text-left p-3 text-blue-600 hover:bg-blue-50 cursor-pointer';
    criar.textContent = `+ Criar "${q}" com valor R$ 0,00`;
    criar.onclick = () => selecionarProduto(q, 0);
    resultados.appendChild(criar);

    resultados.classList.remove('hidden');
}

function fecharResultados(){
    if (!resultados) return;
    resultados.innerHTML = '';
    resultados.classList.add('hidden');
}

// ============ SELECIONAR PRODUTO ============
function selecionarProduto(nome, preco){
    if (finalizado) return;

    itens.push({
        nome: nome,
        preco: Number(preco) || 0,
        qtd: 1
    });

    fecharResultados();
    busca.value = '';
    renderTudo();
}

// ================= HELPERS =================
function escapeHtml(valor){
    return String(valor ?? '')
        .replaceAll('&','&amp;')
        .replaceAll('<','&lt;')
        .replaceAll('>','&gt;')
        .replaceAll('"','&quot;')
        .replaceAll("'",'&#039;');
}

function removerErro(input){
    input.classList.remove('input-erro');
}

function limparErros(){
    document.querySelectorAll('.input-erro').forEach(el => el.classList.remove('input-erro'));
}

function focarComScroll(el){
    el.scrollIntoView({ behavior:'smooth', block:'center' });
    setTimeout(() => el.focus(), 200);
}

function formatarReal(v){
    return Number(v || 0).toLocaleString('pt-BR',{
        minimumFractionDigits:2,
        maximumFractionDigits:2
    });
}

function mascaraMoeda(valor){
    valor = String(valor || '').replace(/\D/g,'');
    valor = (Number(valor) / 100).toFixed(2);
    valor = valor.replace('.',',');
    valor = valor.replace(/\B(?=(\d{3})+(?!\d))/g,'.');
    return valor;
}

function moedaParaNumero(valor){
    if(!valor) return 0;
    valor = String(valor).replace(/\./g,'');
    valor = valor.replace(',','.');
    return Number.parseFloat(valor) || 0;
}

function normalizarItem(item){
    return {
        nome: String(item.nome || '').trim(),
        preco: Number(item.preco || 0),
        qtd: Math.max(1, Number.parseInt(item.qtd || 1, 10))
    };
}

// ================= TOTAL =================
function atualizarTotal(){
    const total = itens.reduce((s, i) => s + (Number(i.preco || 0) * Number(i.qtd || 0)), 0);
    document.getElementById('totalGeral').innerText = 'R$ ' + formatarReal(total);
}

// ================= CLIENTE =================
if (clienteInput) {
    clienteInput.addEventListener('input', () => {
        if(clienteInput.value.trim()) removerErro(clienteInput);
    });
}

// ================= CRIAR ITEM =================
function criarItem(i, index){
    i = normalizarItem(i);
    itens[index] = i;

    const div = document.createElement('div');
    div.className = 'bg-gray-50 border rounded-xl p-3';

    div.innerHTML = `
    <div class="space-y-3">
        <div class="flex justify-between items-center gap-2">
            <input class="nome border rounded-lg px-3 py-3 w-full ${finalizado ? 'campo-bloqueado' : ''}"
                value="${escapeHtml(i.nome)}"
                placeholder="Nome do item"
                ${finalizado ? 'readonly' : ''}>

            ${finalizado ? '' : '<button type="button" class="remover text-red-500 text-xl px-2">✕</button>'}
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-2">
            <div class="flex items-center border rounded-lg overflow-hidden w-full">
                ${finalizado ? '' : `<button type="button" onclick="menosQtd(${index})" class="menos px-4 py-3 bg-gray-100 text-lg font-bold">−</button>`}

                <input type="number"
                    onchange="updateQtd(${index}, this.value)"
                    min="1"
                    class="qtd w-full text-center outline-none py-3 ${finalizado ? 'campo-bloqueado' : ''}"
                    value="${i.qtd}"
                    ${finalizado ? 'readonly' : ''}>

                ${finalizado ? '' : `<button type="button" onclick="maisQtd(${index})" class="mais px-4 py-3 bg-gray-100 text-lg font-bold">+</button>`}
            </div>

            <input class="preco border rounded-lg px-3 py-3 w-full text-right ${finalizado ? 'campo-bloqueado' : ''}"
                value="${formatarReal(i.preco)}"
                placeholder="0,00"
                ${finalizado ? 'readonly' : ''}>

            <div class="subtotal border rounded-lg px-3 py-3 bg-white font-bold text-right">
                R$ ${formatarReal(i.preco * i.qtd)}
            </div>
        </div>
    </div>`;

    if (!finalizado) {
        const nome = div.querySelector('.nome');
        const qtd = div.querySelector('.qtd');
        const preco = div.querySelector('.preco');

        nome.addEventListener('input', () => {
            itens[index].nome = nome.value;
            if (nome.value.trim()) removerErro(nome);
        });

        qtd.addEventListener('input', () => {
            itens[index].qtd = Math.max(1, Number(qtd.value) || 1);
            atualizarItem(index, div);
        });

        preco.addEventListener('input', () => {
            preco.value = mascaraMoeda(preco.value);
            itens[index].preco = moedaParaNumero(preco.value);
            atualizarItem(index, div);
        });

        div.querySelector('.remover').onclick = () => {
            itens.splice(index, 1);
            renderTudo();
        };
    }

    return div;
}

function atualizarItem(index, el){
    const i = normalizarItem(itens[index]);
    itens[index] = i;
    el.querySelector('.subtotal').innerText = 'R$ ' + formatarReal(i.preco * i.qtd);
    atualizarTotal();
}

function renderTudo(){
    lista.innerHTML = '';

    if (!itens.length) {
        lista.innerHTML = '<div class="text-gray-500 bg-gray-50 border rounded-xl p-4">Nenhum item informado.</div>';
        atualizarTotal();
        return;
    }

    itens.forEach((i, index) => lista.appendChild(criarItem(i, index)));
    atualizarTotal();
}

function addItem(){
    if (finalizado) return;
    itens.push({ nome:'', preco:0, qtd:1 });
    renderTudo();
}

// ================= QUANTIDADE =================
function updateQtd(i, valor){
    if (finalizado) return;
    itens[i].qtd = Math.max(1, Number(valor) || 1);
    renderTudo();
}

function menosQtd(i){
    if (finalizado) return;
    itens[i].qtd = Math.max(1, Number(itens[i].qtd || 1) - 1);
    renderTudo();
}

function maisQtd(i){
    if (finalizado) return;
    itens[i].qtd = Number(itens[i].qtd || 1) + 1;
    renderTudo();
}

function validarFormulario(){
    limparErros();

    const cliente = clienteInput.value.trim();

    if(!cliente){
        clienteInput.classList.add('input-erro');
        focarComScroll(clienteInput);
        toast('Informe o cliente', 'erro');
        return false;
    }

    if (!itens.length) {
        toast('Informe pelo menos um item', 'erro');
        return false;
    }

    let primeiroErro = null;

    itens = itens.map(normalizarItem);

    document.querySelectorAll('#lista .nome').forEach((input, index) => {
        if (!itens[index] || !itens[index].nome) {
            input.classList.add('input-erro');
            primeiroErro = primeiroErro || input;
        }
    });

    if (primeiroErro) {
        focarComScroll(primeiroErro);
        toast('Preencha o nome de todos os itens', 'erro');
        return false;
    }

    return true;
}

// ================= SALVAR =================
async function salvar(){
    if (finalizado) {
        toast('Orçamento finalizado não pode ser editado', 'erro');
        return;
    }

    if (!validarFormulario()) return;

    const textoOriginal = btnSalvar.innerText;
    btnSalvar.disabled = true;
    btnSalvar.innerText = 'Salvando...';
    btnSalvar.classList.add('opacity-70', 'cursor-not-allowed');

    try {
        const res = await fetch('/api/orcamentos_update.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                id: id,
                cliente: clienteInput.value.trim(),
                whatsapp: whatsappInput.value,
                itens: itens.map(normalizarItem)
            })
        });

        const data = await res.json().catch(() => null);

        if (!res.ok || !data || data.ok === false) {
            throw new Error((data && data.erro) ? data.erro : 'Erro ao salvar alterações');
        }

        toast('Editado com sucesso!', 'sucesso');

        setTimeout(() => {
            location.href = '/pages/orcamentos.php';
        }, 900);

    } catch (err) {
        toast(err.message || 'Erro de conexão ao salvar', 'erro');
        btnSalvar.disabled = false;
        btnSalvar.innerText = textoOriginal;
        btnSalvar.classList.remove('opacity-70', 'cursor-not-allowed');
    }
}

renderTudo();
</script>

<?php layout_footer(); ?>
