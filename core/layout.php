<?php
/**
 * Layout principal - Orçamentaria Express
 *
 * Estrutura consolidada do menu lateral, menu mobile, barra inferior e permissões.
 */

function layout_user_nivel_safe(): string {
    if (function_exists('user_nivel')) {
        return (string)user_nivel();
    }

    if (function_exists('user')) {
        $u = user();
        return (string)($u['nivel'] ?? '');
    }

    return (string)($_SESSION['user']['nivel'] ?? '');
}


function layout_user_email_safe(): string {
    if (function_exists('user')) {
        $u = user();
        if (is_array($u) && !empty($u['email'])) {
            return strtolower(trim((string)$u['email']));
        }
    }

    if (!empty($_SESSION['user']['email'])) {
        return strtolower(trim((string)$_SESSION['user']['email']));
    }

    return '';
}

function layout_is_admin_safe(): bool {
    if (function_exists('isAdmin')) {
        return (bool)isAdmin();
    }

    return layout_user_nivel_safe() === 'admin';
}

function layout_empresa_id_safe(): int {
    if (function_exists('empresa_id')) {
        return (int)empresa_id();
    }

    return (int)($_SESSION['empresa_id'] ?? 0);
}

function layout_is_bacuri_admin_safe(): bool {
    if (!layout_is_admin_safe()) {
        return false;
    }

    if (function_exists('isBacuriAdmin')) {
        return (bool)isBacuriAdmin();
    }

    $empresaId = layout_empresa_id_safe();
    $email = layout_user_email_safe();

    // Fallbacks explícitos para a empresa-mãe do banco atual.
    // Isso evita que o item Empresas suma no mobile quando a validação por slug falhar.
    if ($empresaId === 4 || $email === 'digitalbacuri@gmail.com') {
        return true;
    }

    if ($empresaId <= 0) {
        return false;
    }

    try {
        global $pdo;

        if ((!isset($pdo) || !($pdo instanceof PDO)) && is_file(__DIR__ . '/../config/db.php')) {
            require_once __DIR__ . '/../config/db.php';
        }

        if (isset($pdo) && $pdo instanceof PDO) {
            $stmt = $pdo->prepare('SELECT slug FROM empresas WHERE id = ? LIMIT 1');
            $stmt->execute([$empresaId]);
            return (string)$stmt->fetchColumn() === 'bacuri-digital';
        }
    } catch (Throwable $e) {
        error_log('[LAYOUT] Falha ao validar slug da empresa: ' . $e->getMessage());
    }

    return false;
}

function layout_url_is_active(string $href): bool {
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $hrefPath = parse_url($href, PHP_URL_PATH) ?: $href;

    $uri = '/' . ltrim($uri, '/');
    $hrefPath = '/' . ltrim($hrefPath, '/');

    $uri = rtrim($uri, '/') ?: '/';
    $hrefPath = rtrim($hrefPath, '/') ?: '/';

    if ($hrefPath === '/') {
        return $uri === '/';
    }

    return $uri === $hrefPath;
}

function layout_nav_items(bool $isBacuriAdmin): array {
    $items = [
        ['href' => '/perfil.php', 'label' => 'Meu Perfil', 'short' => 'Perfil', 'emoji' => '🧑'],
        ['href' => '/index.php', 'label' => 'Painel', 'short' => 'Painel', 'emoji' => '📈'],
        ['href' => '/novo_orcamento.php', 'label' => 'Novo Orçamento', 'short' => 'Novo', 'emoji' => '📝'],
        ['href' => '/pages/orcamentos.php', 'label' => 'Orçamentos', 'short' => 'Orç.', 'emoji' => '📋'],
        ['href' => '/produto.php', 'label' => 'Produtos', 'short' => 'Prod.', 'emoji' => '📦'],
        ['href' => '/planos.php', 'label' => 'Planos', 'short' => 'Planos', 'emoji' => '💎'],
    ];

    if ($isBacuriAdmin) {
        $items[] = ['href' => '/admin/empresas.php', 'label' => 'Empresas', 'short' => 'Empresas', 'emoji' => '⚙️'];
    }

    return $items;
}

function layout_mobile_nav_items(bool $isBacuriAdmin): array {
    // Barra inferior mobile com os atalhos principais visíveis.
    // Perfil continua disponível no menu completo ☰ para liberar espaço na barra inferior.
    $items = [
        ['href' => '/index.php', 'label' => 'Painel', 'short' => 'Painel', 'emoji' => '📈'],
        ['href' => '/novo_orcamento.php', 'label' => 'Novo Orçamento', 'short' => 'Novo', 'emoji' => '📝'],
        ['href' => '/pages/orcamentos.php', 'label' => 'Orçamentos', 'short' => 'Orç.', 'emoji' => '📋'],
        ['href' => '/produto.php', 'label' => 'Produtos', 'short' => 'Prod.', 'emoji' => '📦'],
        ['href' => '/planos.php', 'label' => 'Planos', 'short' => 'Planos', 'emoji' => '💎'],
    ];

    if ($isBacuriAdmin) {
        $items[] = ['href' => '/admin/empresas.php', 'label' => 'Empresas', 'short' => 'Emp.', 'emoji' => '⚙️'];
    }

    return $items;
}

function layout_nav_link(string $href, string $label, string $emoji = ''): void {
    $active = layout_url_is_active($href);

    $classes = $active
        ? 'flex items-center gap-2 rounded-xl bg-blue-50 px-3 py-2 text-blue-700 font-semibold border border-blue-100 min-h-[40px] text-sm'
        : 'flex items-center gap-2 rounded-xl px-3 py-2 text-gray-700 hover:bg-gray-100 hover:text-blue-700 transition min-h-[40px] text-sm';

    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="app-nav-link ' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="w-5 text-center">' . htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</a>';
}

function layout_mobile_icon_link(array $item): void {
    $href = (string)($item['href'] ?? '#');
    $label = (string)($item['short'] ?? $item['label'] ?? 'Item');
    $emoji = (string)($item['emoji'] ?? '•');
    $active = layout_url_is_active($href);

    $classes = $active
        ? 'mobile-icon-link flex flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1.5 text-blue-700 bg-blue-50 border border-blue-100 font-bold min-h-[52px]'
        : 'mobile-icon-link flex flex-col items-center justify-center gap-0.5 rounded-xl px-1 py-1.5 text-gray-700 hover:bg-gray-50 active:bg-gray-100 min-h-[52px]';

    echo '<a href="' . htmlspecialchars($href, ENT_QUOTES, 'UTF-8') . '" class="' . htmlspecialchars($classes, ENT_QUOTES, 'UTF-8') . '">';
    echo '<span class="text-[20px] leading-none">' . htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '<span class="text-[9px] leading-none whitespace-nowrap max-w-full overflow-hidden text-ellipsis">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
    echo '</a>';
}

function layout_header($titulo = "Sistema") {
    $nivelAtual = layout_user_nivel_safe();
    $homeUrl = (function_exists('auth_home_url')) ? auth_home_url() : '/index.php';
    $isBacuriAdmin = layout_is_bacuri_admin_safe();
    $navItems = layout_nav_items($isBacuriAdmin);
    $mobileNavItems = layout_mobile_nav_items($isBacuriAdmin);
    $mobileColumns = max(1, count($mobileNavItems));
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8') ?></title>

<!-- Open Graph -->
<meta property="og:title" content="Proposta Comercial - Orçamentaria Express" />
<meta property="og:description" content="Orçamentaria Express" />
<meta property="og:url" content="https://www.mecanica.bacuridigital.com/" />
<meta property="og:type" content="website" />

<link rel="icon" type="image/png" href="/imagens/orcamentaria_express.png">
<link rel="shortcut icon" href="/imagens/orcamentaria_express.png">

<meta property="og:image" content="https://www.mecanica.bacuridigital.com/imagens/orcamentaria_express.png" />
<meta property="og:image:secure_url" content="https://www.mecanica.bacuridigital.com/imagens/orcamentaria_express.png" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="500" />
<meta property="og:image:height" content="420" />
<meta property="og:locale" content="pt_BR" />
<meta name="twitter:card" content="summary_large_image" />

<link rel="stylesheet" href="/assets/css/app.css?v=20260511">

<style>
.sidebar{ transition: transform .3s ease; }

@media (max-width: 767px){
  body.menu-mobile-open{ overflow:hidden; }
  #sidebar{ box-shadow: 0 20px 60px rgba(15,23,42,.22); }
  #mobileQuickNav{ padding-bottom: max(8px, env(safe-area-inset-bottom)); }
  #mobileQuickNav .mobile-icon-link{ min-width:0; }

  /* Logo reduzida apenas dentro do menu mobile. */
  #sidebar .layout-brand-logo{
    width: 150px !important;
    max-width: 150px;
  }
}

.badge { 
  padding:2px 6px;
  border-radius:4px;
  font-size:10px;
  font-weight:bold; 
}

.badge-green { background:#28a745; color:#fff; }
.badge-red   { background:#dc3545; color:#fff; }
.badge-blue  { background:#007bff; color:#fff; }
.badge-yellow{ background:#ffc107; color:#000; }
.badge-gray  { background:#6c757d; color:#fff; }

#toast {
  position: fixed;
  bottom: 92px;
  right: 16px;
  left: 16px;
  padding: 12px 16px;
  border-radius: 12px;
  color: #fff;
  font-size: 14px;
  font-weight: bold;
  background: #111;
  opacity: 0;
  transform: translateY(20px);
  transition: all .3s ease;
  z-index: 9999;
  max-width: 520px;
  margin-left: auto;
}

@media (min-width: 768px){
  #toast{
    bottom: 20px;
    left: auto;
    right: 20px;
    border-radius: 6px;
    max-width: min(420px, calc(100vw - 32px));
  }
}

#toast.ok,
#toast.sucesso { background: #28a745; color: #fff; }
#toast.erro    { background: #dc3545; color: #fff; }
#toast.warn    { background: #ffc107; color: #000; }
#toast.info    { background: #007bff; color: #fff; }

img {
  animation: pulsar 5s infinite;
}

@keyframes pulsar {
  0% {
    transform: scale(1);
  }
  80% {
    transform: scale(1);
  }
  90% {
    transform: scale(0.95);
  }
  100% {
    transform: scale(1);
  }
}
</style>

</head>

<body class="bg-gray-100">

<div class="flex min-h-screen">

    <!-- OVERLAY MOBILE -->
    <div id="overlay"
         onclick="closeMenu()"
         class="fixed inset-0 bg-black bg-opacity-50 hidden z-40 md:hidden">
    </div>

    <!-- SIDEBAR / MENU COMPLETO -->
    <aside id="sidebar"
        class="sidebar fixed inset-y-0 left-0 md:static z-50 w-[86vw] max-w-[360px] md:w-56 min-h-[100dvh] md:min-h-screen bg-white border-r p-4 md:p-3
               transform -translate-x-full md:translate-x-0 overflow-y-auto">

        <div class="md:hidden flex items-center justify-between mb-4">
            <div>
                <div class="text-xs uppercase tracking-[0.18em] text-gray-400 font-bold"></div>
                <div class="font-bold text-gray-900"></div>
            </div>
            <button type="button" onclick="closeMenu()" class="rounded-2xl border px-2 py-1 text-xs font-medium bg-gray-50 text-gray-700 active:scale-[.98]">
                Fechar
            </button>
        </div>

        <div class="text-center mb-4">
            <a href="<?= htmlspecialchars($homeUrl, ENT_QUOTES, 'UTF-8') ?>" class="inline-block app-nav-link">
                <img src="/imagens/orcamentaria_express.png"
                     title="Orçamentaria Express"
                     alt="Orçamentaria Express"
                     class="layout-brand-logo mx-auto w-[105px] h-auto">
            </a>
            
            <div class="orc-title">
              <h1 class="mt-3 font-bold text-gray-500 leading-snug px-2 text-xl">
                ORÇAMENTARIA
              </h1>
              <span class="text-xs">Orçamentos Profissionais</span>
            </div>

        </div>
        
        <nav class="flex flex-col gap-1 md:gap-0.5">
            <?php foreach ($navItems as $item): ?>
                <?php layout_nav_link((string)$item['href'], (string)$item['label'], (string)$item['emoji']); ?>
            <?php endforeach; ?>

            <div class="my-2 border-t"></div>

            <a href="/logout.php" class="app-nav-link flex items-center gap-2 rounded-xl px-3 py-2 min-h-[40px] text-red-600 hover:bg-red-50 transition text-sm">
                <span class="w-5 text-center">👋</span>
                <span>Sair</span>
            </a>
        </nav>
    </aside>

    <!-- CONTEÚDO -->
    <div class="flex-1 flex flex-col min-w-0">

        <!-- HEADER MOBILE -->
        <header class="md:hidden bg-white border-b p-3 flex items-center justify-between sticky top-0 z-30">
            <button onclick="openMenu()" class="text-sm rounded-xl border px-3 py-2 bg-white font-semibold text-gray-700 active:scale-[.98]">
                ☰ Menu
            </button>

        <?php
        $empresaNomeHeader = 'ORÇAMENTARIA EXPRESS';
        
        try {
            $empresaIdHeader = (int)($_SESSION['empresa_id'] ?? 0);
        
            if ($empresaIdHeader > 0) {
                global $pdo;
        
                if ((!isset($pdo) || !($pdo instanceof PDO)) && is_file(__DIR__ . '/../config/db.php')) {
                    require_once __DIR__ . '/../config/db.php';
                }
        
                if (isset($pdo) && $pdo instanceof PDO) {
                    $stmtEmpresaHeader = $pdo->prepare("
                        SELECT nome 
                        FROM empresas 
                        WHERE id = ? 
                        LIMIT 1
                    ");
                    $stmtEmpresaHeader->execute([$empresaIdHeader]);
        
                    $nomeBancoHeader = trim((string)$stmtEmpresaHeader->fetchColumn());
        
                    if ($nomeBancoHeader !== '') {
                        $empresaNomeHeader = mb_strtoupper($nomeBancoHeader, 'UTF-8');
                    }
                }
            }
        } catch (Throwable $e) {
            error_log('[LAYOUT] Falha ao buscar nome da empresa no header mobile: ' . $e->getMessage());
        }
        ?>
        
        <span class="text-xl text-gray-500 max-w-[46vw] truncate"  style="padding-right: 70px;" title="<?= htmlspecialchars($empresaNomeHeader, ENT_QUOTES, 'UTF-8') ?>">
            <b><?= htmlspecialchars($empresaNomeHeader, ENT_QUOTES, 'UTF-8') ?></b>
        </span>

            <a href="/logout.php" class="text-red-600 text-xs font-semibold">Sair</a>
        </header>

        <main class="p-4 md:p-6 pb-28 md:pb-6">

<?php
}

function layout_footer() {
    $isBacuriAdmin = layout_is_bacuri_admin_safe();
    $navItems = layout_nav_items($isBacuriAdmin);
    $mobileNavItems = layout_mobile_nav_items($isBacuriAdmin);
    $mobileColumns = max(1, count($mobileNavItems));
?>
        </main>
    </div>
</div>

<!-- MENU DE ÍCONES MOBILE SEMPRE VISÍVEL -->
<nav id="mobileQuickNav" class="md:hidden fixed bottom-0 inset-x-0 z-40 bg-white/95 backdrop-blur border-t shadow-[0_-8px_24px_rgba(15,23,42,.08)] px-2 pt-1">
    <div class="grid gap-1" style="grid-template-columns: repeat(<?= (int)$mobileColumns ?>, minmax(0, 1fr));">
        <?php foreach ($mobileNavItems as $item): ?>
            <?php layout_mobile_icon_link($item); ?>
        <?php endforeach; ?>
    </div>
</nav>

<!-- ================= TOAST ================= -->
<div id="toast"></div>

<script>
// ================= VOLTAR COMPATÍVEL DESKTOP/MOBILE =================
window.voltarCompat = function(event, fallbackUrl){
    if(event && typeof event.preventDefault === 'function'){
        event.preventDefault();
    }

    const destinoSeguro = fallbackUrl || '/index.php';

    try {
        const referrer = document.referrer ? new URL(document.referrer) : null;
        const origemAtual = window.location.origin;

        if(referrer && referrer.origin === origemAtual && window.history.length > 1){
            window.history.back();
            return false;
        }
    } catch(e) {}

    window.location.href = destinoSeguro;
    return false;
};

// ================= TOAST =================
window.toast = function(msg, tipo='info'){
    const el = document.getElementById('toast');
    if(!el) return;

    el.className = '';
    el.classList.add(tipo || 'info');
    el.innerText = msg || '';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';

    clearTimeout(window.__toastTimer);
    window.__toastTimer = setTimeout(()=>{
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
    }, 3000);
};

function isMobileLayout(){
    return window.matchMedia && window.matchMedia('(max-width: 767px)').matches;
}

function openMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if(!sidebar) return;

    sidebar.classList.remove('-translate-x-full');

    if(isMobileLayout()){
        document.body.classList.add('menu-mobile-open');
        if(overlay){ overlay.classList.remove('hidden'); }
    } else {
        document.body.classList.remove('menu-mobile-open');
        if(overlay){ overlay.classList.add('hidden'); }
    }
}

function closeMenu(){
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('overlay');

    if(!sidebar) return;

    if(isMobileLayout()){
        sidebar.classList.add('-translate-x-full');
        document.body.classList.remove('menu-mobile-open');
    } else {
        sidebar.classList.remove('-translate-x-full');
        document.body.classList.remove('menu-mobile-open');
    }

    if(overlay){ overlay.classList.add('hidden'); }
}

function toggleMenu(){
    const sidebar = document.getElementById('sidebar');
    if(!sidebar) return;

    if(sidebar.classList.contains('-translate-x-full')){
        openMenu();
    } else {
        closeMenu();
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if(isMobileLayout()){
        closeMenu();
    } else {
        openMenu();
    }
});

window.addEventListener('resize', () => {
    if(isMobileLayout()){
        closeMenu();
    } else {
        openMenu();
    }
});

// Fecha o menu completo depois de escolher um item.
document.querySelectorAll('.app-nav-link').forEach(link => {
    link.addEventListener('click', () => {
        if(isMobileLayout()){
            closeMenu();
        }
    });
});

// Permite fechar o menu pelo teclado em celulares/tablets com teclado externo.
document.addEventListener('keydown', (e) => {
    if(e.key === 'Escape'){
        closeMenu();
    }
});
</script>

</body>
</html>

<?php
}
