<?php
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
);

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

function gerarSlugBase(string $nome): string {
    $nome = trim($nome);

    if (function_exists('iconv')) {
        $convertido = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nome);
        if ($convertido !== false) {
            $nome = $convertido;
        }
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $nome));
    $slug = trim($slug, '-');

    return $slug !== '' ? $slug : 'empresa';
}

function gerarSlugUnico(PDO $pdo, string $nome): string {
    $base = gerarSlugBase($nome);
    $slug = $base;
    $i = 2;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM empresas WHERE slug = ?');

    while (true) {
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }

        $slug = $base . '-' . $i;
        $i++;
    }
}

function redirecionarLogin(string $erro = 'google'): void {
    header('Location: /login.php?erro=' . urlencode($erro));
    exit;
}

if (!empty($_GET['error'])) {
    redirecionarLogin('google_cancelado');
}

if (empty($_GET['code'])) {
    redirecionarLogin('google_codigo');
}

$stateRecebido = $_GET['state'] ?? '';
$stateSessao = $_SESSION['google_oauth_state'] ?? '';
unset($_SESSION['google_oauth_state']);

if ($stateSessao === '' || !hash_equals($stateSessao, $stateRecebido)) {
    error_log('[GOOGLE_OAUTH] State inválido no callback.');
    redirecionarLogin('google_state');
}

try {
    $client = new Google_Client();
    $client->setClientId(app_config_required('GOOGLE_CLIENT_ID'));
    $client->setClientSecret(app_config_required('GOOGLE_CLIENT_SECRET'));
    $client->setRedirectUri(app_config('GOOGLE_REDIRECT_URI', app_base_url() . '/login_google.php'));
    $client->addScope('email');
    $client->addScope('profile');

    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (!empty($token['error'])) {
        error_log('[GOOGLE_OAUTH] Token error: ' . json_encode($token));
        redirecionarLogin('google_token');
    }

    if (empty($token['access_token'])) {
        redirecionarLogin('google_token');
    }

    $client->setAccessToken($token['access_token']);

    $oauth = new Google_Service_Oauth2($client);
    $googleUser = $oauth->userinfo->get();

    $email = strtolower(trim((string)$googleUser->email));
    $nome = trim((string)$googleUser->name);
    $googleId = trim((string)$googleUser->id);

    if ($email === '' || $googleId === '') {
        redirecionarLogin('google_dados');
    }

    if ($nome === '') {
        $nome = $email;
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("\n        SELECT \n            u.*,\n            e.status AS empresa_status\n        FROM usuarios u\n        JOIN empresas e ON e.id = u.empresa_id\n        WHERE u.email = ?\n        LIMIT 1\n    ");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario) {
        if (($usuario['empresa_status'] ?? '') !== 'ativa') {
            $pdo->rollBack();
            redirecionarLogin('empresa_suspensa');
        }

        $empresaId = (int)$usuario['empresa_id'];
        $userId = (int)$usuario['id'];

        if (($usuario['google_id'] ?? '') !== $googleId) {
            $stmt = $pdo->prepare('UPDATE usuarios SET google_id = ? WHERE id = ?');
            $stmt->execute([$googleId, $userId]);
        }
    } else {
        $slug = gerarSlugUnico($pdo, $nome);

        $stmt = $pdo->prepare("\n            INSERT INTO empresas (nome, slug, status, plano_id, assinatura)\n            VALUES (?, ?, 'ativa', 1, 'FREE')\n        ");
        $stmt->execute([$nome, $slug]);
        $empresaId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("\n            INSERT INTO usuarios (empresa_id, nome, email, google_id, nivel)\n            VALUES (?, ?, ?, ?, 'admin')\n        ");
        $stmt->execute([$empresaId, $nome, $email, $googleId]);
        $userId = (int)$pdo->lastInsertId();

        $stmt = $pdo->prepare("\n            INSERT INTO produtos (empresa_id, nome, preco, deleted_at)\n            SELECT ?, nome, preco, NULL\n            FROM modelos_produtos\n        ");
        $stmt->execute([$empresaId]);
    }

    $stmt = $pdo->prepare("\n        SELECT \n            u.id,\n            u.empresa_id,\n            u.nome,\n            u.email,\n            u.nivel,\n            u.google_id,\n            e.status AS empresa_status\n        FROM usuarios u\n        JOIN empresas e ON e.id = u.empresa_id\n        WHERE u.id = ?\n        LIMIT 1\n    ");
    $stmt->execute([$userId]);
    $userSession = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userSession || ($userSession['empresa_status'] ?? '') !== 'ativa') {
        $pdo->rollBack();
        redirecionarLogin('empresa_suspensa');
    }

    $stmt = $pdo->prepare('SELECT nome, whatsapp FROM empresas WHERE id = ? LIMIT 1');
    $stmt->execute([$empresaId]);
    $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

    $pdo->commit();

    session_regenerate_id(true);

    $empresaStatus = $userSession['empresa_status'] ?? null;
    unset($userSession['empresa_status']);

    $_SESSION['empresa_id'] = $empresaId;
    $_SESSION['user_id'] = $userId;
    $_SESSION['empresa_status'] = $empresaStatus;
    $_SESSION['user'] = $userSession;

    if (empty($empresa['nome']) || empty($empresa['whatsapp'])) {
        header('Location: /completar_cadastro.php');
    } else {
        header('Location: /novo_orcamento.php');
    }
    exit;
} catch (Throwable $e) {
    if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('[LOGIN_GOOGLE] ' . $e->getMessage());
    redirecionarLogin('google');
}
