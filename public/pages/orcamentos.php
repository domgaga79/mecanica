<?php
$root = dirname(__DIR__, 2);

require_once $root . '/config/db.php';
require_once $root . '/core/auth.php';
require_once $root . '/core/empresa.php';
require_once $root . '/core/layout.php';

checkAuth();

$empresa_id = empresa_id();

if (!$empresa_id) {
    die('Empresa não identificada');
}

layout_header('Orçamentos');
?>

<style>
@media (max-width: 767px){
    .orcamentos-page-wrap{
        padding-bottom: 8px;
    }

    .orcamentos-topbar{
        gap: 12px;
    }

    .orcamentos-topbar-title{
        min-width: 0;
    }

    .orcamentos-actions{
        display: grid !important;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .orcamentos-actions > button,
    .orcamentos-actions > a{
        width: 100%;
        min-height: 40px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-align: center;
        white-space: nowrap;
    }
}
</style>

<div class="orcamentos-page-wrap space-y-4">

    <div class="orcamentos-topbar flex items-start justify-between mb-4">
        <div class="orcamentos-topbar-title">
            <h1 class="text-xl md:text-2xl font-bold leading-tight">📄 Orçamentos</h1>
            <p class="text-sm text-gray-500 mt-1">Gerencie propostas, links públicos, PDFs, envio por WhatsApp e status.</p>
        </div>

        <a href="/index.php"
           onclick="return voltarCompat(event, '/index.php')"
           class="text-gray-500 hover:text-black font-semibold whitespace-nowrap text-right leading-tight pt-1">
            &larr; Voltar
        </a>
    </div>

    <div class="flex justify-end mb-4">
        <a href="/novo_orcamento.php"
           class="inline-flex items-center justify-center bg-black text-white px-4 py-2 rounded-lg text-sm font-semibold hover:bg-gray-800 w-full md:w-auto">
            + Novo orçamento
        </a>
    </div>

<!-- FILTROS -->
<div class="bg-white p-4 rounded-xl shadow mb-4">
    <div class="grid grid-cols-1 md:grid-cols-12 gap-2">
        <div class="md:col-span-5">
            <label for="busca" class="block text-xs font-semibold text-gray-500 mb-1">Busca</label>
            <input id="busca" type="text"
                   placeholder="Cliente, WhatsApp, ID ou Nº..."
                   class="border p-2 rounded w-full"
                   autocomplete="off">
        </div>

        <div class="md:col-span-3">
            <label for="status" class="block text-xs font-semibold text-gray-500 mb-1">Status</label>
            <select id="status" class="border p-2 rounded w-full">
                <option value="">Todos</option>
                <option value="rascunho">Rascunho</option>
                <option value="enviado">Enviado</option>
                <option value="visualizado">Visualizado</option>
                <option value="aprovado">Aprovado</option>
                <option value="recusado">Recusado</option>
            </select>
        </div>

        <div class="md:col-span-2">
            <label for="limit" class="block text-xs font-semibold text-gray-500 mb-1">Por página</label>
            <select id="limit" class="border p-2 rounded w-full">
                <option value="10">10</option>
                <option value="20">20</option>
                <option value="50">50</option>
            </select>
        </div>

        <div class="md:col-span-2 flex items-end gap-2">
            <button onclick="aplicarFiltros()"
                    class="bg-black text-white px-4 py-2 rounded w-full hover:bg-gray-800">
                Filtrar
            </button>
        </div>
    </div>
</div>

<!-- RESUMO DA BUSCA -->
<div id="resumoBusca" class="hidden bg-blue-50 border border-blue-100 text-blue-900 p-3 rounded-xl mb-4 text-sm"></div>

<!-- LISTA -->
<div id="lista" class="space-y-3">
    <div class="bg-white p-4 rounded-xl shadow text-gray-500">Carregando orçamentos...</div>
</div>

<!-- PAGINAÇÃO -->
<div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mt-4">
    <div id="infoPaginacao" class="text-sm text-gray-500"></div>

    <div class="flex gap-2">
        <button id="btnAnterior" style="cursor:pointer" onclick="prevPage()" class="px-3 py-2 bg-gray-200 rounded disabled:opacity-50 disabled:cursor-not-allowed">
            Anterior
        </button>
        <button id="btnProximo" style="cursor:pointer" onclick="nextPage()" class="px-3 py-2 bg-gray-200 rounded disabled:opacity-50 disabled:cursor-not-allowed">
            Próximo
        </button>
    </div>
</div>

</div>

<script>

let page = 1;
let pages = 1;
let total = 0;
let carregando = false;

function escapeHtml(value){
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function moeda(valor){
    const n = Number(valor || 0);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function erroApi(data, fallback){
    return (data && (data.erro || data.message)) || fallback || 'Erro inesperado';
}

async function carregar(p = 1) {
    if (carregando) return;

    carregando = true;
    page = p;

    const busca = document.getElementById('busca').value || '';
    const status = document.getElementById('status').value || '';
    const limit = document.getElementById('limit').value || '10';

    document.getElementById('lista').innerHTML =
        `<div class="bg-white p-4 rounded-xl shadow text-gray-500">Carregando orçamentos...</div>`;

    const url = `/api/orcamentos_listar.php?page=${page}&limit=${encodeURIComponent(limit)}&q=${encodeURIComponent(busca)}&status=${encodeURIComponent(status)}`;

    try {
        const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
        const json = await res.json();

        if (!json.ok) {
            document.getElementById('lista').innerHTML =
                `<div class="bg-white p-4 rounded-xl shadow text-red-600">${escapeHtml(erroApi(json, 'Erro ao carregar dados'))}</div>`;
            atualizarPaginacao();
            return;
        }

        pages = Number(json.paginacao?.pages || 1);
        total = Number(json.paginacao?.total || 0);

        render(json.data || []);
        renderResumo(json);
        atualizarPaginacao();

    } catch (err) {
        document.getElementById('lista').innerHTML =
            `<div class="bg-white p-4 rounded-xl shadow text-red-600">Erro ao carregar dados</div>`;
        atualizarPaginacao();
    } finally {
        carregando = false;
    }
}

function renderResumo(json){
    const el = document.getElementById('resumoBusca');
    const resumo = json.resumo || null;

    if (!resumo) {
        el.classList.add('hidden');
        el.innerHTML = '';
        return;
    }

    el.classList.remove('hidden');
    el.innerHTML = `
        <div class="grid grid-cols-1 md:grid-cols-4 gap-2">
            <div><b>${Number(resumo.total_filtrado || 0)}</b><br><span class="text-xs">orçamentos encontrados</span></div>
            <div><b>${moeda(resumo.total_proposto || 0)}</b><br><span class="text-xs">total proposto filtrado</span></div>
            <div><b>${moeda(resumo.total_aprovado || 0)}</b><br><span class="text-xs">aprovado filtrado</span></div>
            <div><b>${Number(resumo.quantidade_itens || 0)}</b><br><span class="text-xs">itens nos orçamentos filtrados</span></div>
        </div>
    `;
}

function aplicarFiltros() {
    page = 1;
    carregar(1);
}

document.getElementById('busca').addEventListener('keydown', function (e) {
    if (e.key === 'Enter') aplicarFiltros();
});

document.getElementById('status').addEventListener('change', aplicarFiltros);
document.getElementById('limit').addEventListener('change', aplicarFiltros);

function render(data) {
    const el = document.getElementById('lista');

    if (!data.length) {
        el.innerHTML = `<div class="bg-white p-4 rounded-xl shadow text-gray-500">Nenhum orçamento encontrado</div>`;
        return;
    }

    el.innerHTML = data.map(o => {
        const cliente = escapeHtml(o.cliente_nome || 'Cliente não informado');
        const whatsapp = escapeHtml(o.cliente_whatsapp || '');
        const validade = o.validade ? `<br>⏳ Validade: ${escapeHtml(formatarDataCurta(o.validade))}` : '';
        const aprovado = o.aprovado_em ? `<br>✅ Aprovado em: ${escapeHtml(formatarData(o.aprovado_em))}` : '';

        return `
            <div class="bg-white p-4 rounded-xl shadow border border-gray-100">
                <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-2">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <b>#${Number(o.numero || o.id)} - ${cliente}</b>
                            ${badgeStatus(o.status)}
                        </div>
                        <div class="text-xs text-gray-500 mt-1">
                            Criado em ${escapeHtml(formatarData(o.created_at))}
                        </div>
                    </div>

                    <div class="text-left md:text-right">
                        <div class="text-lg font-bold">${moeda(o.valor_total)}</div>
                        <div class="text-xs text-gray-500">${Number(o.total_itens || 0)} item(ns)</div>
                    </div>
                </div>

                <div class="text-sm text-gray-600 mt-3 leading-relaxed">
                    ${whatsapp ? `📱 WhatsApp: ${whatsapp}<br>` : ''}
                    🔗 Token: <span class="font-mono text-xs">${escapeHtml(o.token || '')}</span>
                    ${validade}
                    ${aprovado}
                </div>

                <div class="mt-3">
                    ${acoesOrcamento(o)}
                </div>
            </div>
        `;
    }).join('');
}

function atualizarPaginacao(){
    document.getElementById('btnAnterior').disabled = page <= 1;
    document.getElementById('btnProximo').disabled = page >= pages;

    document.getElementById('infoPaginacao').innerText =
        total > 0 ? `Página ${page} de ${pages} • ${total} registro(s)` : '';
}

function nextPage() {
    if (page < pages) carregar(page + 1);
}

function prevPage() {
    if (page > 1) carregar(page - 1);
}

function formatarData(dataString){
    if(!dataString) return '';

    const d = new Date(String(dataString).replace(' ', 'T'));
    if(isNaN(d.getTime())) return dataString;

    return d.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
}

function formatarDataCurta(dataString){
    if(!dataString) return '';

    const partes = String(dataString).split('-');
    if(partes.length === 3){
        return `${partes[2]}/${partes[1]}/${partes[0]}`;
    }

    return dataString;
}

function acoesOrcamento(o){
    const id = Number(o.id);
    const status = o.status || '';
    const finalizado = ['aprovado','recusado'].includes(status);
    const urlPublica = escapeHtml(o.url_publica || (o.token ? `/publico.php?t=${encodeURIComponent(o.token)}` : ''));
    const pdfUrl = escapeHtml(o.pdf_url || `/pdf_orcamento.php?id=${id}`);
    const whatsapp = escapeHtml(o.url_whatsapp || '');

    let html = `<div class="orcamentos-actions flex flex-wrap gap-2">`;

    if(!finalizado){
        html += `
            <button onclick="location.href='/editar_orcamento.php?id=${id}'"
                    class="inline-flex items-center justify-center bg-yellow-500 text-white px-3 py-2 rounded text-xs font-semibold">
                ✏️ Editar
            </button>`;
    }

    html += `
        <button onclick="window.open('${pdfUrl}', '_blank')"
                class="inline-flex items-center justify-center bg-blue-500 text-white px-3 py-2 rounded text-xs font-semibold">
            📄 PDF
        </button>`;

    if(whatsapp && !finalizado){
        html += `
            <button onclick="window.open('${whatsapp}', '_blank')"
                    class="inline-flex items-center justify-center bg-green-500 text-white px-3 py-2 rounded text-xs font-semibold">
                📱 WhatsApp
            </button>`;
    }

    if(urlPublica){
        html += `
            <button onclick="window.open('${urlPublica}', '_blank')"
                    class="inline-flex items-center justify-center bg-purple-500 text-white px-3 py-2 rounded text-xs font-semibold">
                🔗 Abrir link
            </button>
            <button onclick="copiarLink('${urlPublica}')"
                    class="inline-flex items-center justify-center bg-purple-100 text-purple-800 px-3 py-2 rounded text-xs font-semibold">
                Copiar link
            </button>`;
    }

    if(status === 'rascunho'){
        html += `
            <button onclick="alterarStatus(${id},'enviado')"
                    class="inline-flex items-center justify-center bg-blue-600 text-white px-3 py-2 rounded text-xs font-semibold">
                📤 Enviar
            </button>`;
    }

    if(['enviado','visualizado'].includes(status)){
        html += `
            <button onclick="alterarStatus(${id},'aprovado')"
                    class="inline-flex items-center justify-center bg-green-600 text-white px-3 py-2 rounded text-xs font-semibold">
                ✅ Aprovar
            </button>

            <button onclick="alterarStatus(${id},'recusado')"
                    class="inline-flex items-center justify-center bg-red-600 text-white px-3 py-2 rounded text-xs font-semibold">
                ❌ Recusar
            </button>`;
    }

    html += `
        <button onclick="duplicar(this, ${id})"
                class="inline-flex items-center justify-center bg-gray-500 text-white px-3 py-2 rounded text-xs font-semibold">
            📑 Duplicar
        </button>`;

    html += `</div>`;
    return html;
}

async function copiarLink(url){
    const link = url.startsWith('http') ? url : window.location.origin + url;

    try {
        await navigator.clipboard.writeText(link);
        toast('Link copiado', 'sucesso');
    } catch (e) {
        prompt('Copie o link:', link);
    }
}

async function alterarStatus(id, status){
    const nomes = {
        enviado: 'enviar este orçamento',
        aprovado: 'aprovar este orçamento',
        recusado: 'recusar este orçamento'
    };

    if(['aprovado','recusado'].includes(status)){
        if(!confirm(`Confirmar: ${nomes[status]}?`)) return;
    }

    try {
        const res = await fetch('/api/orcamentos_status.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: 'id=' + encodeURIComponent(id) + '&status=' + encodeURIComponent(status)
        });

        const data = await res.json();

        if(data.ok){
            toast('Status atualizado: ' + status, 'sucesso');

            if(status === 'enviado' && data.url_whatsapp){
                window.open(data.url_whatsapp, '_blank');
            }

            carregar(page);
        } else {
            toast(erroApi(data, 'Erro ao atualizar'), 'erro');
        }
    } catch(e){
        toast('Falha de conexão', 'erro');
    }
}

function badgeStatus(status){
    const base = 'text-xs px-2 py-1 rounded font-semibold text-white';

    switch(status){
        case 'rascunho':
            return `<span class="${base} bg-gray-500">Rascunho</span>`;
        case 'enviado':
            return `<span class="${base} bg-blue-600">Enviado</span>`;
        case 'visualizado':
            return `<span class="${base} bg-purple-600">Visualizado</span>`;
        case 'aprovado':
            return `<span class="${base} bg-green-600">Aprovado</span>`;
        case 'recusado':
            return `<span class="${base} bg-red-600">Recusado</span>`;
        default:
            return `<span class="${base} bg-gray-400">${escapeHtml(status || 'Indefinido')}</span>`;
    }
}

async function duplicar(btn, id){
    const textoOriginal = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Duplicando...';

    try {
        const res = await fetch('/api/orcamentos_duplicar.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
            body: 'id=' + encodeURIComponent(id)
        });

        const data = await res.json();

        if (data.ok) {
            toast('Orçamento duplicado com sucesso', 'sucesso');
            carregar(1);
        } else {
            toast(erroApi(data, 'Erro ao duplicar'), 'erro');
            btn.disabled = false;
            btn.innerText = textoOriginal;
        }
    } catch (e) {
        toast('Erro de conexão', 'erro');
        btn.disabled = false;
        btn.innerText = textoOriginal;
    }
}

carregar();
</script>

<?php layout_footer(); ?>
