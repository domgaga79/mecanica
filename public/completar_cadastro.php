<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$empresa_id = $_SESSION['empresa_id'] ?? null;
if (!$empresa_id) {
    header('Location: /login.php');
    exit;
}

// Bloqueia continuação de cadastro caso a empresa tenha sido suspensa.
$stmt = $pdo->prepare("SELECT nome, whatsapp, slug, status FROM empresas WHERE id = ? LIMIT 1");
$stmt->execute([$empresa_id]);
$empresa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$empresa) {
    session_destroy();
    header('Location: /login.php');
    exit;
}

if (($empresa['status'] ?? '') !== 'ativa') {
    $_SESSION = [];
    session_destroy();
    header('Location: /login.php?erro=empresa_suspensa');
    exit;
}

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empresa_nome = trim($_POST['empresa'] ?? '');
    $telefone     = trim($_POST['telefone'] ?? '');

    if (!$empresa_nome || !$telefone) {
        $erro = 'Informe o nome da empresa e o telefone/WhatsApp';
    } else {
        // Gerar slug único baseado no nome
        $base_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $empresa_nome));
        $base_slug = trim($base_slug, '-');
        $slug = $base_slug ?: 'empresa';
        $i = 1;

        while(true){
            $check = $pdo->prepare("SELECT id FROM empresas WHERE slug=? AND id != ?");
            $check->execute([$slug, $empresa_id]);
            if(!$check->fetch()) break;
            $slug = $base_slug . '-' . $i;
            $i++;
        }

        // Atualizar empresa
        $stmt = $pdo->prepare("UPDATE empresas SET nome = :nome, whatsapp = :tel, slug = :slug WHERE id = :id AND status = 'ativa'");
        $stmt->execute([
            'nome' => $empresa_nome,
            'tel'  => $telefone,
            'slug' => $slug,
            'id'   => $empresa_id
        ]);

        header('Location: /novo_orcamento.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <!-- Linha fundamental para o funcionamento da responsividade no mobile -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Completar Cadastro</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<!-- Adicionado px-4 para o container não colar nas bordas em celulares antigos/pequenos -->
<body class="bg-gray-100 flex items-center justify-center min-h-screen px-4">

<!-- O 'w-full max-w-md' garante que ele use a largura total no mobile e limite o tamanho no desktop -->
<div class="bg-white p-6 rounded-lg shadow-md w-full max-w-md">
    <h2 class="text-xl font-bold mb-4">Completar Cadastro</h2>

    <?php if($erro): ?>
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($erro) ?>
        </div>
    <?php endif; ?>

    <!-- Removi a tag <style> antiga e passei o espaçamento direto nas classes do Tailwind (mb-3) -->
    <form method="POST" class="space-y-4">
        <div>
            <label for="empresa" class="block font-medium text-gray-700 mb-1">Nome da Empresa</label>
            <input name="empresa" id="empresa" placeholder="Nome da Empresa" 
                   value="<?= htmlspecialchars($empresa['nome'] ?? '') ?>"
                   class="w-full border rounded-lg px-3 py-2 outline-none focus:border-gray-500" required>
        </div>

        <div>
            <label for="telefone" class="block font-medium text-gray-700 mb-1">WhatsApp</label>
            <input name="telefone" id="telefone" placeholder="WhatsApp" 
                   value="<?= htmlspecialchars($empresa['whatsapp'] ?? '') ?>"
                   class="w-full border rounded-lg px-3 py-2 outline-none focus:border-gray-500" required>
        </div>

        <button class="w-full bg-gray-900 text-white py-3 rounded-lg font-semibold hover:bg-black transition mt-2">
            Salvar e continuar
        </button>
    </form>
</div>

</body>
</html>