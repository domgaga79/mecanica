<?php

// ================= SESSÃO SEGURA =================
function auth_is_https_request(): bool {
    return (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
    );
}

function auth_start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    if (headers_sent()) {
        session_start();
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => auth_is_https_request(),
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

auth_start_session();

// ================= HELPERS INTERNOS =================
function auth_is_api_request(): bool {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';

    return (
        strpos($uri, '/api/') !== false ||
        stripos($accept, 'application/json') !== false ||
        strtolower($requestedWith) === 'xmlhttprequest'
    );
}

function auth_pdo(): ?PDO {
    global $pdo;

    if (isset($pdo) && $pdo instanceof PDO) {
        return $pdo;
    }

    $dbPath = dirname(__DIR__) . '/config/db.php';

    if (file_exists($dbPath)) {
        require_once $dbPath;
    }

    return (isset($pdo) && $pdo instanceof PDO) ? $pdo : null;
}

function auth_destroy_session(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        auth_start_session();
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => $params['secure'] ?? auth_is_https_request(),
            'httponly' => $params['httponly'] ?? true,
            'samesite' => $params['samesite'] ?? 'Lax'
        ]);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

function auth_response_unauthenticated(bool $api = false): void {
    $api = $api || auth_is_api_request();

    if ($api) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'erro' => 'Não autenticado'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: /login.php?erro=sessao_expirada');
    exit;
}

function auth_response_suspended(bool $api = false): void {
    auth_destroy_session();

    $api = $api || auth_is_api_request();

    if ($api) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'erro' => 'Empresa suspensa. Entre em contato com o suporte.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    header('Location: /login.php?erro=empresa_suspensa');
    exit;
}

function auth_response_validation_error(bool $api = false): void {
    $api = $api || auth_is_api_request();

    if ($api) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'erro' => 'Não foi possível validar o acesso da empresa.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    http_response_code(500);
    echo 'Não foi possível validar o acesso da empresa.';
    exit;
}

function auth_empresa_ativa(): bool {
    $empresaId = (int)($_SESSION['empresa_id'] ?? 0);

    if ($empresaId <= 0) {
        return false;
    }

    $pdo = auth_pdo();

    if (!$pdo) {
        auth_response_validation_error();
    }

    $stmt = $pdo->prepare('SELECT status FROM empresas WHERE id = ? LIMIT 1');
    $stmt->execute([$empresaId]);
    $status = $stmt->fetchColumn();

    $_SESSION['empresa_status'] = $status ?: null;

    return $status === 'ativa';
}

// ================= HELPERS PÚBLICOS =================
function user(){
    return $_SESSION['user'] ?? null;
}

function empresa_id(){
    return (int)($_SESSION['empresa_id'] ?? 0);
}

function isAdmin(){
    return (user()['nivel'] ?? '') === 'admin';
}

function usuario_id(){
    return (int)($_SESSION['user_id'] ?? 0);
}

// ================= AUTH =================
function checkAuth(){
    if (empty($_SESSION['user_id'])) {
        auth_response_unauthenticated(false);
    }

    if (!auth_empresa_ativa()) {
        auth_response_suspended(false);
    }
}

function checkAuthApi(){
    if (empty($_SESSION['user_id'])) {
        auth_response_unauthenticated(true);
    }

    if (!auth_empresa_ativa()) {
        auth_response_suspended(true);
    }
}

// ================= AUTORIZAÇÃO POR NÍVEL =================
function user_nivel(): string {
    return (string)(user()['nivel'] ?? '');
}

function auth_home_url(): string {
    $nivel = user_nivel();

    switch ($nivel) {
        case 'admin':
            return '/index.php';
        case 'vendedor':
            return '/pages/orcamentos.php';
        default:
            return '/index.php';
    }
}

function auth_home_label(): string {
    $nivel = user_nivel();

    switch ($nivel) {
        case 'admin':
            return 'Voltar ao dashboard';
        case 'vendedor':
            return 'Voltar para orçamentos';
        default:
            return 'Voltar para minha área';
    }
}

function auth_response_forbidden(string $mensagem = 'Você não tem permissão para acessar esta área.', bool $api = false): void {
    $api = $api || auth_is_api_request();

    if ($api) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => false,
            'erro' => $mensagem
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $motivo = rawurlencode($mensagem);
    header('Location: /acesso_negado.php?motivo=' . $motivo);
    exit;
}

function requireNivel(array $niveisPermitidos, bool $api = false): void {
    if ($api) {
        checkAuthApi();
    } else {
        checkAuth();
    }

    $nivelAtual = user_nivel();

    if (!in_array($nivelAtual, $niveisPermitidos, true)) {
        auth_response_forbidden('Seu usuário não tem permissão para acessar esta área.', $api);
    }
}

function requireAdmin(bool $api = false): void {
    requireNivel(['admin'], $api);
}

function isBacuriAdmin(): bool {
    if (!isAdmin()) {
        return false;
    }

    $empresaAtual = (int)empresa_id();
    if ($empresaAtual <= 0) {
        return false;
    }

    $pdo = auth_pdo();
    if (!$pdo) {
        return false;
    }

    $stmt = $pdo->prepare('SELECT slug FROM empresas WHERE id = ? LIMIT 1');
    $stmt->execute([$empresaAtual]);

    return (string)$stmt->fetchColumn() === 'bacuri-digital';
}
