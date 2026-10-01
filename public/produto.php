<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/layout.php';

checkAuth();
$empresa_id = empresa_id();

$id = isset($_GET['id']) ? $_GET['id'] : null;
$titulo = $id ? "Editar Produto" : "Novo Produto";

layout_header($titulo);
?>

<style>
.prod-wrap{max-width:980px;margin:0 auto;padding-bottom:84px}.prod-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.prod-title h2{font-size:clamp(1.35rem,2vw,1.9rem);font-weight:800;color:#111827;margin:0}.prod-title p{margin:4px 0 0;color:#6b7280;font-size:.95rem;line-height:1.45}.prod-back{color:#4b5563;font-weight:500;text-decoration:none;white-space:nowrap;border:0;background:transparent;cursor:pointer}.prod-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;box-shadow:0 12px 30px rgba(15,23,42,.08);overflow:hidden}.prod-progress{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:16px;background:linear-gradient(180deg,#f8fafc,#fff);border-bottom:1px solid #e5e7eb}.prod-step-pill{display:flex;align-items:center;gap:10px;padding:12px;border-radius:18px;border:1px solid #e5e7eb;background:#fff;color:#6b7280}.prod-step-pill strong{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:.9rem;flex:0 0 auto}.prod-step-pill span{font-weight:900;font-size:.92rem}.prod-step-pill small{display:block;font-size:.72rem;font-weight:700;color:#9ca3af;line-height:1.2}.prod-step-pill.active{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.prod-step-pill.active strong{background:#2563eb;color:#fff}.prod-step-pill.done{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.prod-step-pill.done strong{background:#16a34a;color:#fff}.prod-body{padding:20px}.prod-step{display:none}.prod-step.active{display:block}.prod-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.prod-section-head h3{font-size:1.25rem;font-weight:900;color:#111827;margin:0}.prod-section-head p{margin:4px 0 0;color:#6b7280;font-size:.95rem;line-height:1.45}.prod-help{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:16px;padding:12px 14px;margin-bottom:16px;font-size:.92rem;line-height:1.45}.prod-grid{display:grid;grid-template-columns:1fr 220px;gap:14px}.prod-field label{display:flex;align-items:center;justify-content:space-between;gap:10px;font-size:.88rem;font-weight:900;color:#374151;margin:0 0 7px 2px}.prod-field small{color:#9ca3af;font-weight:700}.prod-input{width:100%;border:1px solid #d1d5db;border-radius:16px;padding:14px 14px;font-size:1rem;background:#fff;outline:none;transition:.18s}.prod-input:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12)}.prod-money{text-align:right;font-weight:900;color:#111827}.prod-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:18px}.prod-actions-left,.prod-actions-right{display:flex;align-items:center;gap:10px;flex-wrap:wrap}.prod-btn{border:0;border-radius:16px;padding:12px 16px;font-weight:900;cursor:pointer;transition:.18s;display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:46px}.prod-btn:disabled{opacity:.65;cursor:not-allowed}.prod-btn-primary{background:#16a34a;color:#fff;box-shadow:0 10px 22px rgba(22,163,74,.22)}.prod-btn-primary:hover{background:#15803d}.prod-btn-blue{background:#2563eb;color:#fff;box-shadow:0 10px 22px rgba(37,99,235,.22)}.prod-btn-blue:hover{background:#1d4ed8}.prod-btn-light{background:#f3f4f6;color:#374151}.prod-btn-light:hover{background:#e5e7eb}.prod-btn-danger{background:#b91c1c;color:#fff}.prod-btn-danger:hover{background:#991b1b}.prod-alert{display:none;margin-bottom:14px;border-radius:16px;padding:12px 14px;font-weight:800;font-size:.92rem}.prod-alert.show{display:block}.prod-alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.prod-alert.ok{background:#ecfdf5;color:#047857;border:1px solid #bbf7d0}.prod-alert.info{background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe}.prod-review{border:1px solid #e5e7eb;border-radius:18px;overflow:hidden;background:#fff}.prod-review-row{display:flex;justify-content:space-between;gap:16px;padding:14px 16px;border-bottom:1px solid #f3f4f6}.prod-review-row:last-child{border-bottom:0}.prod-review-row span{color:#6b7280;font-weight:800}.prod-review-row strong{color:#111827;text-align:right}.prod-list-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin:18px 0 12px}.prod-list-head h3{font-size:1.15rem;font-weight:900;color:#111827;margin:0}.prod-list-head p{color:#6b7280;font-size:.9rem;margin:3px 0 0}.prod-search-wrap{display:flex;align-items:center;gap:10px;margin-bottom:12px}.prod-search{width:100%;border:1px solid #d1d5db;border-radius:16px;padding:13px 14px;outline:none}.prod-search:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12)}.prod-list{display:grid;gap:10px}.prod-empty{text-align:center;color:#6b7280;background:#f9fafb;border:1px dashed #d1d5db;border-radius:18px;padding:22px}.prod-item{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:12px;box-shadow:0 8px 18px rgba(15,23,42,.05)}.prod-item-grid{display:grid;grid-template-columns:minmax(0,1fr) 160px auto;gap:10px;align-items:center}.prod-item-title{position:relative}.prod-item-title input,.prod-item-price input{width:100%;border:1px solid #d1d5db;border-radius:14px;padding:11px 12px;outline:none}.prod-item-title input:focus,.prod-item-price input:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.1)}.prod-item-price input{text-align:right;font-weight:900}.prod-item-actions{display:flex;align-items:center;gap:8px}.prod-mini{min-height:40px;padding:9px 11px;border-radius:13px;font-size:.88rem}.prod-pagination{display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:10px}.prod-page-info{font-weight:900;color:#4b5563;font-size:.9rem}.prod-stat{display:flex;gap:10px;flex-wrap:wrap;margin-top:14px}.prod-stat-card{background:#f9fafb;border:1px solid #e5e7eb;border-radius:16px;padding:12px 14px;min-width:160px}.prod-stat-card small{display:block;color:#6b7280;font-weight:800;font-size:.78rem}.prod-stat-card strong{display:block;color:#111827;font-weight:900;font-size:1.1rem;margin-top:2px}.prod-tip-list{margin:0;padding-left:18px;color:#374151;line-height:1.55}.prod-tip-list li{margin:5px 0}.prod-mobile-only{display:none}@media (max-width:760px){.prod-wrap{padding-bottom:104px}.prod-top{align-items:flex-start}.prod-progress{grid-template-columns:1fr;padding:12px}.prod-step-pill{padding:10px 11px}.prod-body{padding:15px}.prod-grid{grid-template-columns:1fr}.prod-section-head{display:block}.prod-actions{align-items:stretch;flex-direction:column}.prod-actions-left,.prod-actions-right{width:100%;display:grid;grid-template-columns:1fr}.prod-btn{width:100%}.prod-item-grid{grid-template-columns:1fr}.prod-item-actions{display:grid;grid-template-columns:1fr 1fr}.prod-list-head{align-items:flex-start;flex-direction:column}.prod-review-row{display:block}.prod-review-row strong{display:block;text-align:left;margin-top:4px}.prod-stat-card{width:100%}.prod-mobile-only{display:block}}@media (max-width:420px){.prod-title h2{font-size:1.22rem}.prod-title p{font-size:.86rem}.prod-item-actions{grid-template-columns:1fr}.prod-pagination{display:grid;grid-template-columns:1fr;}.prod-page-info{text-align:center;order:-1}}
</style>

<div class="prod-wrap" id="prodApp" data-etapa="1">

    <div class="prod-top">
        <div class="prod-title">
            <h2><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></h2>
            <p>Cadastre serviços e produtos com nome claro e preço padrão para reutilizar nos próximos orçamentos.</p>
        </div>
        <a href="/index.php" onclick="return voltarCompat(event, '/index.php')" class="prod-back">&larr; Voltar</a>
    </div>

    <div class="prod-card">
        <div class="prod-progress" aria-label="Etapas do cadastro de produtos">
            <button type="button" class="prod-step-pill active" data-pill="1" onclick="setEtapa(1)">
                <strong>1</strong>
                <span>Produto<small>Nome e preço</small></span>
            </button>
            <button type="button" class="prod-step-pill" data-pill="2" onclick="irParaRevisao()">
                <strong>2</strong>
                <span>Conferência<small>Revise antes de salvar</small></span>
            </button>
            <button type="button" class="prod-step-pill" data-pill="3" onclick="setEtapa(3)">
                <strong>3</strong>
                <span>Catálogo<small>Editar e excluir</small></span>
            </button>
        </div>

        <div class="prod-body">
            <div id="prodAlert" class="prod-alert"></div>

            <input type="hidden" id="produto_id" value="<?= htmlspecialchars($id ?? '', ENT_QUOTES, 'UTF-8') ?>">

            <section class="prod-step active" data-step="1">
                <div class="prod-section-head">
                    <div>
                        <h3>Dados do produto</h3>
                        <p>Use nomes fáceis de encontrar no autocomplete do orçamento, por exemplo: “Troca de óleo e filtro”.</p>
                    </div>
                </div>

                <div class="prod-help">
                    Dica: cadastre aqui os itens que aparecem com frequência nos orçamentos. Depois, em <b>Novo Orçamento</b>, basta buscar pelo nome e o preço já entra preenchido.
                </div>

                <div class="prod-grid">
                    <div class="prod-field">
                        <label for="nome">Nome do produto/serviço <small>obrigatório</small></label>
                        <input id="nome" class="prod-input" autocomplete="off" placeholder="Ex.: Revisão de freios">
                    </div>

                    <div class="prod-field">
                        <label for="preco">Preço padrão <small>R$</small></label>
                        <input id="preco" type="text" inputmode="decimal" placeholder="0,00" class="prod-input prod-money">
                    </div>
                </div>

                <div class="prod-stat" aria-label="Resumo do catálogo">
                    <div class="prod-stat-card">
                        <small>Produtos cadastrados</small>
                        <strong id="statProdutos">0</strong>
                    </div>
                    <div class="prod-stat-card">
                        <small>Valor médio</small>
                        <strong id="statMedia">R$ 0,00</strong>
                    </div>
                </div>

                <div class="prod-actions">
                    <div class="prod-actions-left">
                        <button type="button" class="prod-btn prod-btn-light" onclick="limparFormulario()">Limpar</button>
                    </div>
                    <div class="prod-actions-right">
                        <button type="button" class="prod-btn prod-btn-blue" onclick="irParaRevisao()">Continuar para conferência</button>
                    </div>
                </div>
            </section>

            <section class="prod-step" data-step="2">
                <div class="prod-section-head">
                    <div>
                        <h3>Conferência</h3>
                        <p>Confira os dados antes de gravar. Essa informação será usada nos itens dos orçamentos.</p>
                    </div>
                </div>

                <div class="prod-review">
                    <div class="prod-review-row">
                        <span>Produto/serviço</span>
                        <strong id="revNome">—</strong>
                    </div>
                    <div class="prod-review-row">
                        <span>Preço padrão</span>
                        <strong id="revPreco">R$ 0,00</strong>
                    </div>
                    <div class="prod-review-row">
                        <span>Ação</span>
                        <strong id="revAcao">Novo cadastro</strong>
                    </div>
                </div>

                <div class="prod-help" style="margin-top:16px">
                    Se o preço variar por cliente, mantenha aqui um valor base. No orçamento você ainda pode ajustar o preço do item manualmente.
                </div>

                <div class="prod-actions">
                    <div class="prod-actions-left">
                        <button type="button" class="prod-btn prod-btn-light" onclick="setEtapa(1)">Voltar e editar</button>
                    </div>
                    <div class="prod-actions-right">
                        <button type="button" onclick="salvar()" id="btn-salvar" class="prod-btn prod-btn-primary">Salvar Produto</button>
                    </div>
                </div>
            </section>

            <section class="prod-step" data-step="3">
                <div class="prod-section-head">
                    <div>
                        <h3>Catálogo de produtos</h3>
                        <p>Busque, edite preços rapidamente ou remova produtos que não devem mais aparecer nos novos orçamentos.</p>
                    </div>
                </div>

                <div class="prod-help">
                    A exclusão é segura: o produto sai das próximas listagens, mas os orçamentos antigos continuam preservados com o nome e preço gravados no histórico.
                </div>

                <div class="prod-search-wrap">
                    <input id="buscaLista" placeholder="Buscar produto ou serviço..." class="prod-search" autocomplete="off">
                </div>

                <div id="lista-produtos" class="prod-list"></div>

                <div class="prod-pagination">
                    <button id="prev" type="button" class="prod-btn prod-btn-light prod-mini">← Anterior</button>
                    <span id="paginaInfo" class="prod-page-info"></span>
                    <button id="next" type="button" class="prod-btn prod-btn-light prod-mini">Próxima →</button>
                </div>

                <div class="prod-actions">
                    <div class="prod-actions-left">
                        <button type="button" class="prod-btn prod-btn-light" onclick="setEtapa(1)">Cadastrar novo produto</button>
                    </div>
                </div>
            </section>
        </div>
    </div>

</div>

<script>
(function(){
    const el = {
        app: document.getElementById('prodApp'),
        alert: document.getElementById('prodAlert'),
        id: document.getElementById('produto_id'),
        nome: document.getElementById('nome'),
        preco: document.getElementById('preco'),
        btnSalvar: document.getElementById('btn-salvar'),
        revNome: document.getElementById('revNome'),
        revPreco: document.getElementById('revPreco'),
        revAcao: document.getElementById('revAcao'),
        lista: document.getElementById('lista-produtos'),
        busca: document.getElementById('buscaLista'),
        prev: document.getElementById('prev'),
        next: document.getElementById('next'),
        paginaInfo: document.getElementById('paginaInfo'),
        statProdutos: document.getElementById('statProdutos'),
        statMedia: document.getElementById('statMedia')
    };

    let produtos = [];
    let pagina = 1;
    let porPagina = 10;
    let filtro = '';
    let etapaAtual = 1;

    function toastLocal(msg, tipo){
        if (typeof window.toast === 'function') {
            window.toast(msg, tipo || 'info');
            return;
        }
        showAlert(msg, tipo || 'info');
    }

    function showAlert(msg, tipo){
        el.alert.className = 'prod-alert show ' + (tipo === 'erro' ? 'err' : (tipo === 'sucesso' ? 'ok' : 'info'));
        el.alert.textContent = msg || '';
        if (msg) el.alert.scrollIntoView({behavior:'smooth', block:'nearest'});
    }

    function clearAlert(){
        el.alert.className = 'prod-alert';
        el.alert.textContent = '';
    }

    function escapeHtml(valor){
        return String(valor == null ? '' : valor).replace(/[&<>'"]/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
        });
    }

    function formatarMoeda(valor){
        valor = Number(valor || 0);
        if (!isFinite(valor)) valor = 0;
        return valor.toLocaleString('pt-BR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    }

    function moedaParaNumero(valor){
        valor = String(valor || '').replace(/\./g, '').replace(',', '.').replace(/[^0-9.]/g, '');
        return parseFloat(valor) || 0;
    }

    function mascaraMoeda(valor){
        var digitos = String(valor || '').replace(/\D/g, '');
        return formatarMoeda((Number(digitos || 0) / 100));
    }

    function aplicarMascaraMoeda(input){
        if (!input) return;
        input.addEventListener('input', function(){
            input.value = mascaraMoeda(input.value);
        });
        input.addEventListener('blur', function(){
            input.value = formatarMoeda(moedaParaNumero(input.value));
        });
    }

    async function lerJson(res){
        const text = await res.text();
        try {
            return JSON.parse(text);
        } catch (e) {
            console.error('RESPOSTA BRUTA:', text);
            throw new Error('Erro no servidor');
        }
    }

    function erroApi(data, fallback){
        if (data && data.erro) return data.erro;
        if (data && data.message) return data.message;
        return fallback || 'Erro inesperado';
    }

    function produtosFiltrados(){
        const termo = filtro.trim().toLowerCase();
        if (!termo) return produtos.slice();
        return produtos.filter(function(p){
            return String(p.nome || '').toLowerCase().includes(termo);
        });
    }

    function atualizarStats(){
        const total = produtos.length;
        const soma = produtos.reduce(function(acc, p){ return acc + Number(p.preco || 0); }, 0);
        const media = total ? soma / total : 0;
        el.statProdutos.textContent = String(total);
        el.statMedia.textContent = 'R$ ' + formatarMoeda(media);
    }

    function renderReview(){
        const nome = el.nome.value.trim();
        const preco = moedaParaNumero(el.preco.value);
        el.revNome.textContent = nome || '—';
        el.revPreco.textContent = 'R$ ' + formatarMoeda(preco);
        el.revAcao.textContent = el.id.value ? 'Atualizar produto existente' : 'Novo cadastro';
    }

    function validarFormulario(){
        const nome = el.nome.value.trim();
        const preco = moedaParaNumero(el.preco.value);

        if (nome.length < 2) {
            showAlert('Informe um nome de produto/serviço com pelo menos 2 caracteres.', 'erro');
            el.nome.focus();
            return false;
        }

        if (preco < 0) {
            showAlert('Informe um preço válido.', 'erro');
            el.preco.focus();
            return false;
        }

        return true;
    }

    window.setEtapa = function(n){
        etapaAtual = Number(n || 1);
        if (el.app) el.app.setAttribute('data-etapa', String(etapaAtual));

        document.querySelectorAll('.prod-step').forEach(function(step){
            step.classList.toggle('active', Number(step.getAttribute('data-step')) === etapaAtual);
        });

        document.querySelectorAll('.prod-step-pill').forEach(function(pill){
            const p = Number(pill.getAttribute('data-pill'));
            pill.classList.toggle('active', p === etapaAtual);
            pill.classList.toggle('done', p < etapaAtual);
        });

        clearAlert();
        if (etapaAtual === 2) renderReview();
        if (etapaAtual === 3) renderLista();
        window.scrollTo({top: 0, behavior: 'smooth'});
    };

    window.irParaRevisao = function(){
        if (!validarFormulario()) return;
        renderReview();
        window.setEtapa(2);
    };

    window.limparFormulario = function(){
        el.id.value = '';
        el.nome.value = '';
        el.preco.value = '';
        el.nome.focus();
        clearAlert();
    };

    async function carregarLista(){
        try {
            const res = await fetch('/api/produtos.php', {credentials:'same-origin'});
            const data = await lerJson(res);
            produtos = Array.isArray(data.produtos) ? data.produtos : [];
            atualizarStats();
            renderLista();
        } catch(e) {
            console.error(e);
            toastLocal(e.message || 'Erro ao carregar produtos', 'erro');
        }
    }

    function renderLista(){
        if (!el.lista) return;

        el.lista.innerHTML = '';
        const lista = produtosFiltrados();

        if (lista.length === 0) {
            el.lista.innerHTML = '<div class="prod-empty">Nenhum produto encontrado</div>';
            el.paginaInfo.textContent = 'Página 0 de 0';
            el.prev.disabled = true;
            el.next.disabled = true;
            return;
        }

        const totalPaginas = Math.max(1, Math.ceil(lista.length / porPagina));
        if (pagina > totalPaginas) pagina = totalPaginas;

        const inicio = (pagina - 1) * porPagina;
        const fim = inicio + porPagina;

        lista.slice(inicio, fim).forEach(function(p){
            const div = document.createElement('article');
            div.className = 'prod-item';
            div.dataset.id = String(p.id);
            div.innerHTML = `
                <div class="prod-item-grid">
                    <div class="prod-item-title">
                        <input class="nome" value="${escapeHtml(p.nome || '')}" aria-label="Nome do produto">
                    </div>
                    <div class="prod-item-price">
                        <input class="preco" value="${formatarMoeda(p.preco)}" inputmode="decimal" aria-label="Preço do produto">
                    </div>
                    <div class="prod-item-actions">
                        <button type="button" class="prod-btn prod-btn-primary prod-mini salvar">Salvar</button>
                        <button type="button" class="prod-btn prod-btn-danger prod-mini excluir">Excluir</button>
                    </div>
                </div>
            `;

            const nome = div.querySelector('.nome');
            const preco = div.querySelector('.preco');
            const btnSalvarInline = div.querySelector('.salvar');
            const btnExcluir = div.querySelector('.excluir');

            aplicarMascaraMoeda(preco);

            btnSalvarInline.onclick = async function(){
                const nomeValor = nome.value.trim();
                const precoValor = moedaParaNumero(preco.value);

                if (nomeValor.length < 2) {
                    toastLocal('Informe o nome do produto antes de salvar.', 'erro');
                    nome.focus();
                    return;
                }

                btnSalvarInline.disabled = true;
                btnSalvarInline.textContent = 'Salvando...';

                try {
                    const form = new FormData();
                    form.append('id', p.id);
                    form.append('nome', nomeValor);
                    form.append('preco', precoValor);

                    const res = await fetch('/api/produtos_salvar.php', {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    });

                    const data = await lerJson(res);

                    if (data.ok) {
                        toastLocal('Produto atualizado', 'sucesso');
                        await carregarLista();
                    } else {
                        toastLocal(erroApi(data, 'Erro ao salvar'), 'erro');
                    }
                } catch(e) {
                    console.error(e);
                    toastLocal(e.message || 'Erro de conexão ao salvar', 'erro');
                } finally {
                    btnSalvarInline.disabled = false;
                    btnSalvarInline.textContent = 'Salvar';
                }
            };

            btnExcluir.onclick = async function(){
                const nomeConfirm = nome.value.trim() || 'este produto';
                if (!confirm('Excluir "' + nomeConfirm + '" do catálogo?')) return;

                btnExcluir.disabled = true;
                btnExcluir.textContent = 'Excluindo...';

                try {
                    const form = new FormData();
                    form.append('id', p.id);

                    const res = await fetch('/api/produtos_delete.php', {
                        method: 'POST',
                        body: form,
                        credentials: 'same-origin'
                    });

                    const data = await lerJson(res);

                    if (data.ok) {
                        toastLocal('Produto excluído', 'sucesso');
                        await carregarLista();
                    } else {
                        toastLocal(erroApi(data, 'Erro ao excluir'), 'erro');
                    }
                } catch(e) {
                    console.error(e);
                    toastLocal(e.message || 'Erro de conexão ao excluir', 'erro');
                } finally {
                    btnExcluir.disabled = false;
                    btnExcluir.textContent = 'Excluir';
                }
            };

            el.lista.appendChild(div);
        });

        el.paginaInfo.textContent = 'Página ' + pagina + ' de ' + totalPaginas;
        el.prev.disabled = pagina <= 1;
        el.next.disabled = pagina >= totalPaginas;
    }

    async function carregarProduto(id){
        try {
            const res = await fetch('/api/produtos.php?id=' + encodeURIComponent(id), {credentials:'same-origin'});
            const data = await lerJson(res);

            if (data.produto) {
                el.id.value = data.produto.id || id;
                el.nome.value = data.produto.nome || '';
                el.preco.value = formatarMoeda(data.produto.preco || 0);
                window.setEtapa(1);
                return;
            }

            toastLocal('Produto não encontrado', 'erro');
        } catch(e) {
            console.error(e);
            toastLocal(e.message || 'Erro ao carregar produto', 'erro');
        }
    }

    window.salvar = async function(){
        if (!validarFormulario()) return;

        const nome = el.nome.value.trim();
        const preco = moedaParaNumero(el.preco.value);

        el.btnSalvar.disabled = true;
        el.btnSalvar.textContent = 'Salvando...';

        try {
            const form = new FormData();
            if (el.id.value) form.append('id', el.id.value);
            form.append('nome', nome);
            form.append('preco', preco);

            const res = await fetch('/api/produtos_salvar.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            });

            const data = await lerJson(res);

            if (data.ok) {
                toastLocal(el.id.value ? 'Produto atualizado' : 'Produto salvo', 'sucesso');
                window.limparFormulario();
                await carregarLista();
                window.setEtapa(3);
            } else {
                toastLocal(erroApi(data, 'Erro ao salvar'), 'erro');
            }
        } catch(e) {
            console.error(e);
            toastLocal(e.message || 'Erro de conexão ao salvar', 'erro');
        } finally {
            el.btnSalvar.disabled = false;
            el.btnSalvar.textContent = 'Salvar Produto';
        }
    };

    document.addEventListener('DOMContentLoaded', function(){
        aplicarMascaraMoeda(el.preco);

        el.busca.addEventListener('input', function(){
            filtro = el.busca.value || '';
            pagina = 1;
            renderLista();
        });

        el.prev.onclick = function(){
            if (pagina > 1) {
                pagina--;
                renderLista();
            }
        };

        el.next.onclick = function(){
            if (pagina * porPagina < produtosFiltrados().length) {
                pagina++;
                renderLista();
            }
        };

        el.nome.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                el.preco.focus();
            }
        });

        el.preco.addEventListener('keydown', function(e){
            if (e.key === 'Enter') {
                e.preventDefault();
                window.irParaRevisao();
            }
        });

        carregarLista();

        if (el.id.value) {
            carregarProduto(el.id.value);
        }
    });
})();
</script>

<?php layout_footer(); ?>
