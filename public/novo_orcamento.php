<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/layout.php';

checkAuth();

layout_header('Novo Orçamento');
?>

<style>
.orc-wrap{width:100%;max-width:none;margin:0;padding-bottom:92px}.orc-top{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:18px}.orc-title h2{font-size:1.25rem;font-weight:700;line-height:1.2;color:#111827;margin:0}.orc-title p{margin:4px 0 0;color:#6b7280;font-size:.95rem}.orc-back{color:#4b5563;font-weight:700;text-decoration:none;white-space:nowrap}.orc-card{background:#fff;border:1px solid #e5e7eb;border-radius:22px;box-shadow:0 12px 30px rgba(15,23,42,.08);overflow:hidden}.orc-progress{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:16px;background:linear-gradient(180deg,#f8fafc,#fff);border-bottom:1px solid #e5e7eb}.orc-step-pill{display:flex;align-items:center;gap:10px;padding:12px;border-radius:18px;border:1px solid #e5e7eb;background:#fff;color:#6b7280}.orc-step-pill strong{display:flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:999px;background:#f3f4f6;color:#374151;font-size:.9rem}.orc-step-pill span{font-weight:800;font-size:.92rem}.orc-step-pill small{display:block;font-size:.72rem;font-weight:600;color:#9ca3af}.orc-step-pill.active{background:#eff6ff;border-color:#bfdbfe;color:#1d4ed8}.orc-step-pill.active strong{background:#2563eb;color:#fff}.orc-step-pill.done{background:#ecfdf5;border-color:#bbf7d0;color:#047857}.orc-step-pill.done strong{background:#16a34a;color:#fff}.orc-body{padding:20px}.orc-step{display:none}.orc-step.active{display:block}.orc-section-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;margin-bottom:16px}.orc-section-head h3{font-size:1.25rem;font-weight:900;color:#111827;margin:0}.orc-section-head p{margin:4px 0 0;color:#6b7280;font-size:.95rem;line-height:1.45}.orc-help{background:#eff6ff;border:1px solid #bfdbfe;color:#1e40af;border-radius:16px;padding:12px 14px;font-size:.9rem;line-height:1.45;margin-bottom:16px}.orc-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.orc-field label{display:flex;align-items:center;gap:7px;font-weight:800;color:#374151;margin-bottom:7px}.orc-field input,.orc-field textarea{width:100%;border:1px solid #d1d5db;border-radius:16px;padding:14px 15px;font-size:1rem;outline:none;background:#fff;transition:.18s}.orc-field input:focus,.orc-field textarea:focus{border-color:#2563eb;box-shadow:0 0 0 4px rgba(37,99,235,.12)}.orc-field .hint{font-size:.82rem;color:#6b7280;margin-top:6px}.orc-actions{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-top:20px;border-top:1px solid #f3f4f6;padding-top:18px}.orc-btn{border:0;border-radius:16px;padding:14px 18px;font-weight:900;cursor:pointer;min-height:50px;transition:.18s;text-align:center}.orc-btn:disabled{opacity:.6;cursor:not-allowed}.orc-btn-primary{background:#16a34a;color:#fff;box-shadow:0 10px 20px rgba(22,163,74,.22)}.orc-btn-primary:hover{background:#15803d}.orc-btn-blue{background:#2563eb;color:#fff;box-shadow:0 10px 20px rgba(37,99,235,.22)}.orc-btn-blue:hover{background:#1d4ed8}.orc-btn-light{background:#f3f4f6;color:#374151}.orc-btn-light:hover{background:#e5e7eb}.orc-btn-danger{background:#fee2e2;color:#b91c1c}.orc-btn-danger:hover{background:#fecaca}.orc-btn-full{width:100%}.orc-search-wrap{position:relative}.orc-search-row{display:grid;grid-template-columns:1fr auto;gap:10px}.orc-results{position:absolute;left:0;right:0;top:calc(100% + 8px);background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 18px 40px rgba(15,23,42,.18);overflow:hidden;z-index:20;display:none}.orc-result{width:100%;display:flex;align-items:center;justify-content:space-between;gap:10px;padding:13px 14px;background:#fff;border:0;border-bottom:1px solid #f3f4f6;cursor:pointer;text-align:left}.orc-result:hover{background:#f9fafb}.orc-result strong{color:#111827}.orc-result span{color:#16a34a;font-weight:900;white-space:nowrap}.orc-empty{border:1px dashed #cbd5e1;border-radius:18px;padding:18px;text-align:center;color:#64748b;background:#f8fafc}.orc-items{display:flex;flex-direction:column;gap:12px;margin-top:16px}.orc-item{border:1px solid #e5e7eb;background:#fff;border-radius:20px;padding:14px}.orc-item{--orc-remove-slot:36px}.orc-item-head{display:grid;grid-template-columns:minmax(0,1fr) var(--orc-remove-slot);gap:12px;align-items:start;margin-bottom:12px}.orc-item-head .orc-field{min-width:0}.js-remover{width:var(--orc-remove-slot);height:50px;display:flex;align-items:center;justify-content:flex-end}.orc-item-name{font-weight:900;color:#111827;line-height:1.3;word-break:break-word}.orc-item-controls{display:grid;grid-template-columns:160px 1fr 120px;gap:10px;align-items:end;padding-right:calc(var(--orc-remove-slot) + 12px);box-sizing:border-box}.orc-qty{display:grid;grid-template-columns:48px minmax(0,1fr) 48px;border:1px solid #d1d5db;border-radius:14px;overflow:hidden;height:48px;background:#fff}.orc-qty button{width:48px;border:0;background:#f3f4f6;font-size:1.35rem;font-weight:900;cursor:pointer}.orc-qty input{width:100%;min-width:0;text-align:center;border:0;outline:none;font-weight:900}.orc-price input{height:48px;text-align:right}.orc-subtotal{text-align:right;font-weight:900;color:#111827;padding-bottom:12px}.orc-total-box{display:flex;align-items:center;justify-content:space-between;gap:12px;background:#111827;color:#fff;border-radius:20px;padding:16px 18px;margin-top:16px}.orc-total-box span{color:#d1d5db;font-weight:700}.orc-total-box strong{font-size:1.45rem}.orc-review{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:16px}.orc-review-card{border:1px solid #e5e7eb;border-radius:18px;padding:15px;background:#fff}.orc-review-card h4{margin:0 0 8px;font-size:.9rem;color:#6b7280;text-transform:uppercase;letter-spacing:.04em}.orc-review-card p{margin:0;color:#111827;font-weight:800;line-height:1.5}.orc-review-list{border:1px solid #e5e7eb;border-radius:18px;overflow:hidden}.orc-review-row{display:grid;grid-template-columns:1fr auto;gap:10px;padding:13px 15px;border-bottom:1px solid #f3f4f6}.orc-review-row:last-child{border-bottom:0}.orc-review-row small{display:block;color:#6b7280;margin-top:3px}.orc-save-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.orc-sticky-summary{display:none}.orc-tooltip{display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:999px;background:#eef2ff;color:#3730a3;font-size:.78rem;font-weight:900;cursor:help;position:relative}.orc-tooltip:hover::after,.orc-tooltip:focus::after{content:attr(data-tip);position:absolute;left:50%;bottom:calc(100% + 8px);transform:translateX(-50%);background:#111827;color:#fff;width:230px;padding:9px 10px;border-radius:10px;font-size:.78rem;font-weight:600;line-height:1.35;z-index:30;text-align:left}.orc-alert{display:none;border-radius:16px;padding:12px 14px;margin-bottom:14px;font-size:.92rem;font-weight:700}.orc-alert.show{display:block}.orc-alert.err{background:#fef2f2;color:#991b1b;border:1px solid #fecaca}.orc-alert.ok{background:#ecfdf5;color:#065f46;border:1px solid #bbf7d0}.orc-alert.info{background:#eff6ff;color:#1e40af;border:1px solid #bfdbfe}
@media(min-width:768px){.orc-title h2{font-size:1.5rem}}@media(max-width:767px){.orc-wrap{padding:0 0 188px}.orc-top{align-items:center;margin-bottom:12px}.orc-card{border-radius:18px}.orc-progress{grid-template-columns:1fr;gap:7px;padding:12px}.orc-step-pill{padding:10px 12px}.orc-step-pill:not(.active):not(.done){display:none}.orc-body{padding:15px}.orc-section-head{display:block}.orc-grid,.orc-review,.orc-save-grid{grid-template-columns:1fr}.orc-search-row{grid-template-columns:1fr}.orc-item-controls{grid-template-columns:1fr;padding-right:calc(var(--orc-remove-slot) + 12px)}.orc-subtotal{padding-bottom:0}.orc-actions{position:sticky;bottom:132px;background:#fff;margin-left:-15px;margin-right:-15px;padding:12px 15px;border-top:1px solid #e5e7eb;box-shadow:0 -8px 20px rgba(15,23,42,.08);z-index:10}.orc-actions .orc-btn{flex:1}.orc-total-box{margin-bottom:18px}.orc-sticky-summary{display:flex;align-items:center;justify-content:space-between;gap:12px;position:fixed;left:0;right:0;bottom:75px;background:#111827;color:#fff;padding:10px 14px;z-index:30;box-shadow:0 -10px 25px rgba(15,23,42,.18)}.orc-sticky-summary small{display:block;color:#cbd5e1;font-weight:700}.orc-sticky-summary strong{font-size:1.08rem}.orc-tooltip:hover::after,.orc-tooltip:focus::after{left:auto;right:-6px;transform:none;width:210px}.orc-title h2{font-size:1.25rem}.orc-title p{font-size:.85rem}#orcApp[data-etapa="1"]{padding-bottom:32px}#orcApp[data-etapa="1"] .orc-actions{position:static;bottom:auto;background:transparent;margin-left:0;margin-right:0;padding:18px 0 0;border-top:1px solid #f3f4f6;box-shadow:none;z-index:auto}#orcApp[data-etapa="1"]~.orc-sticky-summary{display:none}}
</style>

<div class="orc-wrap" id="orcApp" data-etapa="1">
    <div class="orc-top">
        <div class="orc-title" style="width:100%">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2>📝 Novo Orçamento</h2>
                    <p>Preencha em 3 passos simples: cliente, itens e revisão.</p>
                </div>

                <a href="/pages/orcamentos.php" onclick="return voltarCompat(event, '/pages/orcamentos.php')" class="text-gray-500 hover:text-black font-semibold whitespace-nowrap text-right">&larr; Voltar</a>
            </div>
        </div>
    </div>

    <div class="orc-card">
        <div class="orc-progress" aria-label="Etapas do orçamento">
            <div class="orc-step-pill active" data-pill="1"><strong>1</strong><div><span>Cliente</span><small>Nome e WhatsApp</small></div></div>
            <div class="orc-step-pill" data-pill="2"><strong>2</strong><div><span>Itens</span><small>Serviços e valores</small></div></div>
            <div class="orc-step-pill" data-pill="3"><strong>3</strong><div><span>Revisão</span><small>Conferir e salvar</small></div></div>
        </div>

        <div class="orc-body">
            <div id="orcAlert" class="orc-alert"></div>

            <section class="orc-step active" data-step="1">
                <div class="orc-section-head">
                    <div>
                        <h3>Para quem é este orçamento?</h3>
                        <p>Comece informando o cliente. O WhatsApp será usado para enviar o link do orçamento.</p>
                    </div>
                </div>

                <div class="orc-help">💡 Dica: informe o WhatsApp com DDD. Exemplo: <strong>75999999999</strong>. Use somente números.</div>

                <div class="orc-grid">
                    <div class="orc-field">
                        <label for="cliente">Nome do cliente <span class="orc-tooltip" tabindex="0" data-tip="Digite o nome que aparecerá no orçamento e no PDF.">?</span></label>
                        <input id="cliente" type="text" autocomplete="name" placeholder="Ex.: João Silva">
                        <div class="hint">Esse nome aparecerá no orçamento enviado ao cliente.</div>
                    </div>
                    <div class="orc-field">
                        <label for="whatsapp">WhatsApp <span class="orc-tooltip" tabindex="0" data-tip="Será usado para abrir o WhatsApp com a mensagem pronta. Informe DDD + número.">?</span></label>
                        <input id="whatsapp" type="tel" inputmode="numeric" autocomplete="tel" placeholder="Ex.: 75999999999">
                        <div class="hint">Somente números, com DDD. Pode salvar sem enviar se preferir.</div>
                    </div>
                </div>

                <div class="orc-actions">
                    <a href="/pages/orcamentos.php" class="orc-btn orc-btn-light">Cancelar</a>
                    <button type="button" class="orc-btn orc-btn-blue" data-next="2">Continuar para itens</button>
                </div>
            </section>

            <section class="orc-step" data-step="2">
                <div class="orc-section-head">
                    <div>
                        <h3>O que será incluído?</h3>
                        <p>Busque um serviço já cadastrado ou adicione um item manual quando o serviço ainda não existir.</p>
                    </div>
                </div>

                <div class="orc-help">💡 Valor <strong>R$ 0,00</strong> é permitido para diagnóstico, cortesia ou item apenas informativo.</div>

                <div class="orc-search-wrap">
                    <div class="orc-search-row">
                        <div class="orc-field">
                            <label for="busca">Buscar produto/serviço <span class="orc-tooltip" tabindex="0" data-tip="Digite pelo menos 2 letras. Você pode escolher um cadastro existente ou criar um item manual.">?</span></label>
                            <input id="busca" type="search" autocomplete="off" placeholder="Ex.: Troca de óleo, freio, diagnóstico">
                        </div>
                        <button type="button" class="orc-btn orc-btn-light" id="btnItemManual">+ Item manual</button>
                    </div>
                    <div id="resultados" class="orc-results"></div>
                </div>

                <div id="itens" class="orc-items"></div>
                <div id="emptyItens" class="orc-empty">Nenhum item adicionado ainda. Busque um serviço ou toque em <strong>+ Item manual</strong>.</div>

                <div class="orc-total-box">
                    <span>Total do orçamento</span>
                    <strong id="total">R$ 0,00</strong>
                </div>

                <div class="orc-actions">
                    <button type="button" class="orc-btn orc-btn-light" data-prev="1">Voltar</button>
                    <button type="button" class="orc-btn orc-btn-blue" data-next="3">Revisar orçamento</button>
                </div>
            </section>

            <section class="orc-step" data-step="3">
                <div class="orc-section-head">
                    <div>
                        <h3>Revise antes de salvar</h3>
                        <p>Confira cliente, WhatsApp, itens e total antes de gerar o orçamento.</p>
                    </div>
                </div>

                <div class="orc-review">
                    <div class="orc-review-card">
                        <h4>Cliente</h4>
                        <p id="revCliente">-</p>
                    </div>
                    <div class="orc-review-card">
                        <h4>WhatsApp</h4>
                        <p id="revWhatsapp">-</p>
                    </div>
                </div>

                <div class="orc-review-list" id="revItens"></div>

                <div class="orc-total-box">
                    <span>Total final</span>
                    <strong id="revTotal">R$ 0,00</strong>
                </div>

                <div class="orc-help" style="margin-top:16px">Ao escolher <strong>Salvar e enviar</strong>, o sistema abrirá o PDF e o WhatsApp com a mensagem pronta. Em alguns celulares o navegador pode pedir permissão para abrir a nova aba.</div>

                <div class="orc-actions">
                    <button type="button" class="orc-btn orc-btn-light" data-prev="2">Voltar para editar</button>
                    <div class="orc-save-grid" style="flex:1">
                        <button type="button" class="orc-btn orc-btn-light" id="btnSalvarSemEnviar">Salvar sem enviar</button>
                        <button type="button" class="orc-btn orc-btn-primary" id="btnSalvarEnviar">Salvar e enviar</button>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>

<div class="orc-sticky-summary" id="stickySummary">
    <div><small>Resumo</small><span id="stickyQtd">0 itens</span></div>
    <strong id="stickyTotal">R$ 0,00</strong>
</div>

<script>
(function(){
    'use strict';

    var itens = [];
    var etapaAtual = 1;
    var salvando = false;

    var el = {
        app: document.getElementById('orcApp'),
        alert: document.getElementById('orcAlert'),
        cliente: document.getElementById('cliente'),
        whatsapp: document.getElementById('whatsapp'),
        busca: document.getElementById('busca'),
        resultados: document.getElementById('resultados'),
        itens: document.getElementById('itens'),
        emptyItens: document.getElementById('emptyItens'),
        total: document.getElementById('total'),
        stickyQtd: document.getElementById('stickyQtd'),
        stickyTotal: document.getElementById('stickyTotal'),
        revCliente: document.getElementById('revCliente'),
        revWhatsapp: document.getElementById('revWhatsapp'),
        revItens: document.getElementById('revItens'),
        revTotal: document.getElementById('revTotal'),
        btnSalvarSemEnviar: document.getElementById('btnSalvarSemEnviar'),
        btnSalvarEnviar: document.getElementById('btnSalvarEnviar'),
        btnItemManual: document.getElementById('btnItemManual')
    };

    function toastLocal(msg, tipo){
        if (typeof window.toast === 'function') {
            window.toast(msg, tipo || 'info');
            return;
        }
        showAlert(msg, tipo || 'info');
    }

    function showAlert(msg, tipo){
        el.alert.className = 'orc-alert show ' + (tipo === 'erro' ? 'err' : (tipo === 'sucesso' ? 'ok' : 'info'));
        el.alert.textContent = msg || '';
        if (msg) {
            el.alert.scrollIntoView({behavior:'smooth', block:'nearest'});
        }
    }

    function clearAlert(){
        el.alert.className = 'orc-alert';
        el.alert.textContent = '';
    }

    function escapeHtml(valor){
        return String(valor == null ? '' : valor).replace(/[&<>'"]/g, function(c){
            return {'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'}[c];
        });
    }

    function somenteDigitos(valor){
        return String(valor || '').replace(/\D/g, '');
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

    function total(){
        return itens.reduce(function(soma, item){
            return soma + (Number(item.preco || 0) * Number(item.qtd || 1));
        }, 0);
    }

    function qtdTexto(){
        var qtd = itens.reduce(function(soma, item){ return soma + Number(item.qtd || 0); }, 0);
        if (itens.length === 0) return '0 itens';
        return itens.length + (itens.length === 1 ? ' item' : ' itens') + ' / ' + qtd + (qtd === 1 ? ' unidade' : ' unidades');
    }

    function setEtapa(n){
        etapaAtual = n;
        if (el.app) {
            el.app.setAttribute('data-etapa', String(n));
        }
        document.querySelectorAll('.orc-step').forEach(function(step){
            step.classList.toggle('active', Number(step.getAttribute('data-step')) === n);
        });
        document.querySelectorAll('.orc-step-pill').forEach(function(pill){
            var p = Number(pill.getAttribute('data-pill'));
            pill.classList.toggle('active', p === n);
            pill.classList.toggle('done', p < n);
        });
        clearAlert();
        window.scrollTo({top: 0, behavior: 'smooth'});
        if (n === 3) renderReview();
    }

    function validarCliente(){
        var cliente = el.cliente.value.trim();
        var whatsapp = somenteDigitos(el.whatsapp.value);

        if (cliente.length < 2) {
            showAlert('Informe o nome do cliente para continuar.', 'erro');
            el.cliente.focus();
            return false;
        }

        if (whatsapp !== '' && whatsapp.length < 10) {
            showAlert('Confira o WhatsApp. Informe DDD + número ou deixe vazio para salvar sem envio.', 'erro');
            el.whatsapp.focus();
            return false;
        }

        el.whatsapp.value = whatsapp;
        return true;
    }

    function validarItens(){
        if (itens.length === 0) {
            showAlert('Adicione pelo menos um item ao orçamento.', 'erro');
            el.busca.focus();
            return false;
        }

        for (var i = 0; i < itens.length; i++) {
            if (!String(itens[i].nome || '').trim()) {
                showAlert('Existe um item sem nome. Preencha ou remova o item.', 'erro');
                return false;
            }
            if (Number(itens[i].qtd || 0) <= 0) {
                showAlert('A quantidade dos itens deve ser maior que zero.', 'erro');
                return false;
            }
            if (Number(itens[i].preco || 0) < 0) {
                showAlert('O preço não pode ser negativo.', 'erro');
                return false;
            }
        }

        return true;
    }

    function render(){
        el.itens.innerHTML = '';
        el.emptyItens.style.display = itens.length ? 'none' : 'block';

        itens.forEach(function(item, index){
            var subtotal = Number(item.preco || 0) * Number(item.qtd || 1);

            var box = document.createElement('div');
            box.className = 'orc-item';
            box.setAttribute('data-index', index);

            box.innerHTML = '' +
                '<div class="orc-item-head">' +
                    '<div class="orc-field" style="flex:1;margin:0">' +
                        '<label>Nome do item</label>' +
                        '<input type="text" class="js-nome" value="' + escapeHtml(item.nome) + '" placeholder="Nome do serviço/produto">' +
                    '</div>' +
                    '<button type="button" class="js-remover bg-transparent border-none p-0 m-0 text-lg cursor-pointer" aria-label="Remover item"><img src="imagens/close_red.png" width="20px"></button>' +
                '</div>' +
                '<div class="orc-item-controls">' +
                    '<div>' +
                        '<label style="display:block;font-weight:800;color:#374151;margin-bottom:7px">Quantidade</label>' +
                        '<div class="orc-qty">' +
                            '<button type="button" class="js-menos">−</button>' +
                            '<input type="number" min="1" class="js-qtd" value="' + Number(item.qtd || 1) + '">' +
                            '<button type="button" class="js-mais">+</button>' +
                        '</div>' +
                    '</div>' +
                    '<div class="orc-field orc-price" style="margin:0">' +
                        '<label>Preço unitário</label>' +
                        '<input type="text" inputmode="numeric" class="js-preco" value="' + formatarMoeda(item.preco) + '">' +
                    '</div>' +
                    '<div class="orc-subtotal">R$ ' + formatarMoeda(subtotal) + '</div>' +
                '</div>';

            el.itens.appendChild(box);
        });

        var totalFormatado = 'R$ ' + formatarMoeda(total());
        el.total.textContent = totalFormatado;
        el.stickyTotal.textContent = totalFormatado;
        el.stickyQtd.textContent = qtdTexto();
    }

    function renderReview(){
        el.revCliente.textContent = el.cliente.value.trim() || '-';
        el.revWhatsapp.textContent = el.whatsapp.value.trim() || 'Não informado';
        el.revItens.innerHTML = '';

        itens.forEach(function(item){
            var subtotal = Number(item.preco || 0) * Number(item.qtd || 1);
            var row = document.createElement('div');
            row.className = 'orc-review-row';
            row.innerHTML = '<div><strong>' + escapeHtml(item.nome) + '</strong><small>' + Number(item.qtd || 1) + ' x R$ ' + formatarMoeda(item.preco) + '</small></div><strong>R$ ' + formatarMoeda(subtotal) + '</strong>';
            el.revItens.appendChild(row);
        });

        el.revTotal.textContent = 'R$ ' + formatarMoeda(total());
    }

    function addItem(nome, preco){
        nome = String(nome || '').trim();
        if (!nome) {
            nome = el.busca.value.trim() || 'Item manual';
        }
        itens.push({nome: nome, preco: Number(preco || 0), qtd: 1});
        el.busca.value = '';
        esconderResultados();
        render();
        toastLocal('Item adicionado. Ajuste quantidade e preço se necessário.', 'sucesso');
    }

    function esconderResultados(){
        el.resultados.style.display = 'none';
        el.resultados.innerHTML = '';
    }

    var buscaTimer = null;
    function buscarProdutos(){
        clearTimeout(buscaTimer);
        buscaTimer = setTimeout(async function(){
            var q = el.busca.value.trim();
            if (q.length < 2) {
                esconderResultados();
                return;
            }

            try {
                var res = await fetch('/api/produtos.php?q=' + encodeURIComponent(q), {credentials:'same-origin'});
                var data = await res.json().catch(function(){ return null; });
                var produtos = Array.isArray(data) ? data : ((data && data.produtos) ? data.produtos : []);

                el.resultados.innerHTML = '';

                produtos.slice(0, 8).forEach(function(p){
                    var btn = document.createElement('button');
                    btn.type = 'button';
                    btn.className = 'orc-result';
                    btn.innerHTML = '<strong>' + escapeHtml(p.nome || '') + '</strong><span>R$ ' + formatarMoeda(p.preco || 0) + '</span>';
                    btn.addEventListener('click', function(){ addItem(p.nome || q, Number(p.preco || 0)); });
                    el.resultados.appendChild(btn);
                });

                var manual = document.createElement('button');
                manual.type = 'button';
                manual.className = 'orc-result';
                manual.innerHTML = '<strong>+ Criar item manual: ' + escapeHtml(q) + '</strong><span>R$ 0,00</span>';
                manual.addEventListener('click', function(){ addItem(q, 0); });
                el.resultados.appendChild(manual);

                el.resultados.style.display = 'block';
            } catch (e) {
                esconderResultados();
            }
        }, 250);
    }

    async function salvar(enviarWhatsapp){
        if (salvando) return;
        if (!validarCliente() || !validarItens()) return;

        salvando = true;
        setBotoesSalvar(true);

        var form = new FormData();
        form.append('cliente', el.cliente.value.trim());
        form.append('whatsapp', somenteDigitos(el.whatsapp.value));
        form.append('itens', JSON.stringify(itens.map(function(item){
            return {nome: String(item.nome || '').trim(), preco: Number(item.preco || 0), qtd: Number(item.qtd || 1)};
        })));

        try {
            var res = await fetch('/api/orcamentos_create.php', {
                method: 'POST',
                body: form,
                credentials: 'same-origin'
            });

            var json = await res.json().catch(function(){ return null; });

            if (json && json.ok) {
                toastLocal('Orçamento criado com sucesso.', 'sucesso');

                if (enviarWhatsapp) {
                    if (json.pdf) {
                        window.open(json.pdf, '_blank');
                    } else if (json.id) {
                        window.open('/pdf_orcamento.php?id=' + encodeURIComponent(json.id), '_blank');
                    }

                    if (json.whatsapp) {
                        window.open(json.whatsapp, '_blank');
                    } else {
                        toastLocal('Orçamento salvo, mas o link do WhatsApp não foi retornado.', 'info');
                    }
                }

                window.setTimeout(function(){
                    window.location.href = '/pages/orcamentos.php';
                }, enviarWhatsapp ? 900 : 500);
                return;
            }

            showAlert((json && json.erro) ? json.erro : 'Erro ao salvar orçamento.', 'erro');
        } catch (e) {
            showAlert('Erro de conexão ao salvar orçamento.', 'erro');
        } finally {
            salvando = false;
            setBotoesSalvar(false);
        }
    }

    function setBotoesSalvar(disabled){
        el.btnSalvarSemEnviar.disabled = disabled;
        el.btnSalvarEnviar.disabled = disabled;
        el.btnSalvarSemEnviar.textContent = disabled ? 'Salvando...' : 'Salvar sem enviar';
        el.btnSalvarEnviar.textContent = disabled ? 'Salvando...' : 'Salvar e enviar';
    }

    document.addEventListener('click', function(ev){
        var next = ev.target.closest('[data-next]');
        var prev = ev.target.closest('[data-prev]');

        if (next) {
            var destino = Number(next.getAttribute('data-next'));
            if (destino === 2 && !validarCliente()) return;
            if (destino === 3 && (!validarCliente() || !validarItens())) return;
            setEtapa(destino);
            return;
        }

        if (prev) {
            setEtapa(Number(prev.getAttribute('data-prev')));
            return;
        }

        if (!ev.target.closest('.orc-search-wrap')) {
            esconderResultados();
        }
    });

    el.whatsapp.addEventListener('input', function(){
        el.whatsapp.value = somenteDigitos(el.whatsapp.value);
    });

    el.busca.addEventListener('input', buscarProdutos);
    el.busca.addEventListener('keydown', function(ev){
        if (ev.key === 'Enter') {
            ev.preventDefault();
            var q = el.busca.value.trim();
            if (q) addItem(q, 0);
        }
    });

    el.btnItemManual.addEventListener('click', function(){
        addItem(el.busca.value.trim() || 'Item manual', 0);
    });

    el.itens.addEventListener('click', function(ev){
        var box = ev.target.closest('.orc-item');
        if (!box) return;
        var index = Number(box.getAttribute('data-index'));

        if (ev.target.closest('.js-remover')) {
            itens.splice(index, 1);
            render();
            return;
        }
        if (ev.target.closest('.js-menos')) {
            itens[index].qtd = Math.max(1, Number(itens[index].qtd || 1) - 1);
            render();
            return;
        }
        if (ev.target.closest('.js-mais')) {
            itens[index].qtd = Number(itens[index].qtd || 1) + 1;
            render();
        }
    });

    el.itens.addEventListener('input', function(ev){
        var box = ev.target.closest('.orc-item');
        if (!box) return;
        var index = Number(box.getAttribute('data-index'));

        if (ev.target.classList.contains('js-nome')) {
            itens[index].nome = ev.target.value;
        }

        if (ev.target.classList.contains('js-qtd')) {
            itens[index].qtd = Math.max(1, Number(ev.target.value || 1));
            render();
        }

        if (ev.target.classList.contains('js-preco')) {
            var masked = mascaraMoeda(ev.target.value);
            ev.target.value = masked;
            itens[index].preco = moedaParaNumero(masked);
            render();
        }
    });

    el.btnSalvarSemEnviar.addEventListener('click', function(){ salvar(false); });
    el.btnSalvarEnviar.addEventListener('click', function(){ salvar(true); });

    render();
})();
</script>

<?php layout_footer(); ?>
