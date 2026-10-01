<?php
require_once __DIR__ . '/../core/auth.php';
require_once __DIR__ . '/../core/layout.php';

checkAuth();

$motivo = trim((string)($_GET['motivo'] ?? ''));

if ($motivo === '') {
    $motivo = 'Seu usuário não tem permissão para acessar esta área.';
}

// Evita mensagens enormes ou manipuladas pela URL.
if (mb_strlen($motivo, 'UTF-8') > 220) {
    $motivo = mb_substr($motivo, 0, 220, 'UTF-8') . '...';
}

$voltarUrl = function_exists('auth_home_url') ? auth_home_url() : '/index.php';
$voltarLabel = function_exists('auth_home_label') ? auth_home_label() : 'Voltar para minha área';
$nivel = function_exists('user_nivel') ? user_nivel() : (user()['nivel'] ?? '');
$nome = user()['nome'] ?? 'Usuário';

layout_header('Acesso negado');
?>

<div class="max-w-2xl mx-auto bg-white rounded-2xl shadow p-6 md:p-8">
    <div class="flex items-start gap-4">
        <div class="w-12 h-12 rounded-full bg-red-100 text-red-600 flex items-center justify-center text-2xl shrink-0">
            🚫
        </div>

        <div class="flex-1">
            <h1 class="text-2xl font-bold mb-2">Acesso negado</h1>
            <p class="text-gray-600 mb-4">
                <?= htmlspecialchars($motivo, ENT_QUOTES, 'UTF-8') ?>
            </p>

            <div class="bg-gray-50 border rounded-xl p-4 text-sm text-gray-600 mb-5">
                <div><strong>Usuário:</strong> <?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></div>
                <div><strong>Nível atual:</strong> <?= htmlspecialchars($nivel ?: 'não identificado', ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <div class="flex flex-col sm:flex-row gap-3">
                <a href="<?= htmlspecialchars($voltarUrl, ENT_QUOTES, 'UTF-8') ?>"
                   class="inline-flex justify-center items-center bg-black text-white px-4 py-3 rounded-xl font-semibold hover:bg-gray-800">
                    <?= htmlspecialchars($voltarLabel, ENT_QUOTES, 'UTF-8') ?>
                </a>

                <a href="/logout.php"
                   class="inline-flex justify-center items-center bg-gray-100 text-gray-700 px-4 py-3 rounded-xl font-semibold hover:bg-gray-200">
                    Sair e trocar usuário
                </a>
            </div>
        </div>
    </div>
</div>

<?php layout_footer(); ?>
