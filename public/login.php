<?php
require_once __DIR__ . '/../config/db.php';

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

$erro = null;
$aviso = null;

$mensagensErro = [
    'empresa_suspensa' => 'Empresa suspensa. Entre em contato com o suporte.',
    'sessao_expirada'  => 'Sua sessão expirou. Entre novamente para continuar.',
    'acesso_negado'    => 'Acesso negado. Faça login para continuar.',
    'google'           => 'Não foi possível concluir o login com Google.',
    'google_cancelado' => 'Login com Google cancelado.',
    'google_codigo'    => 'Código de autenticação do Google não recebido.',
    'google_state'     => 'Falha de segurança no login com Google. Tente novamente.',
    'google_token'     => 'Não foi possível validar o token do Google.',
    'google_dados'     => 'Não foi possível obter os dados da conta Google.',
    'google_config'    => 'Login com Google indisponível no momento.',
];

if (!empty($_GET['erro'])) {
    $chaveErro = (string)$_GET['erro'];
    $erro = $mensagensErro[$chaveErro] ?? 'Não foi possível concluir o login.';
}

if (!empty($_GET['logout'])) {
    $aviso = 'Você saiu do sistema com segurança.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));
    $senha = $_POST['senha'] ?? '';

    if ($email === '' || $senha === '') {
        $erro = 'Informe email e senha.';
    } else {
        $stmt = $pdo->prepare("\n            SELECT \n                u.*,\n                e.status AS empresa_status\n            FROM usuarios u\n            JOIN empresas e ON e.id = u.empresa_id\n            WHERE u.email = ?\n            LIMIT 1\n        ");
        $stmt->execute([$email]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && ($user['empresa_status'] ?? '') !== 'ativa') {
            $erro = 'Empresa suspensa. Entre em contato com o suporte.';
        } elseif ($user && !empty($user['senha_hash']) && password_verify($senha, $user['senha_hash'])) {
            session_regenerate_id(true);

            $empresaStatus = $user['empresa_status'] ?? null;
            unset($user['senha_hash'], $user['empresa_status']);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['empresa_id'] = (int)$user['empresa_id'];
            $_SESSION['empresa_status'] = $empresaStatus;
            $_SESSION['user'] = $user;
            $_SESSION['login_at'] = time();

            header('Location: /novo_orcamento.php');
            exit;
        } else {
            $erro = 'Login inválido';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Login - Orçamentaria Express</title>

<meta property="og:title" content="Login - Orçamentaria Express" />
<meta property="og:description" content="Login - Orçamentaria Express" />
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

<link rel="stylesheet" href="/assets/css/app.css?v=20260514">
<link rel="stylesheet" href="/assets/css/login.css?v=20260514">

<style>
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

<body class="login-page">
<main class="login-shell">
  <section class="login-card">
      <div class="login-brand">
          <img class="login-logo" src="/imagens/orcamentaria_express.png" alt="Orçamentaria Express">
      </div>

      <h1 class="login-title">Orçamentos para Mecânica</h1>

      <?php if(!empty($erro)): ?>
        <div class="login-alert login-alert-error">
          <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <?php if(!empty($aviso)): ?>
        <div class="login-alert login-alert-success">
          <?= htmlspecialchars($aviso, ENT_QUOTES, 'UTF-8') ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="login-actions" autocomplete="on">
        <a href="/google_oauth.php" class="login-google-button">
            <img src="https://www.svgrepo.com/show/355037/google.svg" width="20" height="20" alt="Google">
            <span>Entrar com Google</span>
        </a>
      </form>

      <p class="login-footnote">Acesse sua conta para criar, acompanhar e enviar orçamentos.</p>
  </section>
</main>

<div id="toast"
     style="
        position: fixed;
        bottom: 20px;
        right: 20px;
        padding: 12px 16px;
        border-radius: 6px;
        color: #fff;
        font-size: 14px;
        background: #111;
        opacity: 0;
        transform: translateY(20px);
        transition: all .3s ease;
        z-index: 9999;
     ">
</div>

<script>
window.toast = function(msg, tipo='ok'){
    const el = document.getElementById('toast');
    if(!el) return;

    el.classList.remove('erro','warn','ok');
    el.classList.add(tipo);

    el.innerText = msg;

    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';

    setTimeout(()=>{
        el.style.opacity = '0';
        el.style.transform = 'translateY(20px)';
    }, 2500);
};

document.querySelectorAll('input, button')
.forEach(el => {
    el.addEventListener('pointerdown', () => {
        navigator.vibrate?.(15);
    });
});

document.querySelectorAll('a').forEach(link => {
    link.addEventListener('pointerdown', function(e){
        if (!navigator.vibrate) return;

        const url = link.href;
        if (!url || url === '#') return;

        e.preventDefault();

        navigator.vibrate(15);

        setTimeout(() => {
            window.location.href = url;
        }, 80);
    });
});
</script>
</body>
</html>
