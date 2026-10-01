<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$erro = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $empresa_nome = trim($_POST['empresa'] ?? '');
    $nome         = trim($_POST['nome'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $senha        = $_POST['senha'] ?? '';

    if(!$empresa_nome || !$nome || !$email || !$senha){
        $erro = "Preencha todos os campos";
    } else {

        try {
            $pdo->beginTransaction();

            // 🔹 GERAR SLUG ÚNICO
            $base_slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $empresa_nome));
            $slug = $base_slug;
            $i = 1;

            while(true){
                $check = $pdo->prepare("SELECT id FROM empresas WHERE slug=?");
                $check->execute([$slug]);

                if(!$check->fetch()) break;

                $slug = $base_slug . '-' . $i;
                $i++;
            }

            // 🔹 CRIAR EMPRESA
            $stmt = $pdo->prepare("INSERT INTO empresas (nome, slug) VALUES (?, ?)");
            $stmt->execute([$empresa_nome, $slug]);

            $empresa_id = $pdo->lastInsertId();

            // 🔹 CRIAR USUÁRIO ADMIN
            $senha_hash = password_hash($senha, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (empresa_id, nome, email, senha_hash, nivel)
                VALUES (?, ?, ?, ?, 'admin')
            ");
            $stmt->execute([$empresa_id, $nome, $email, $senha_hash]);

            $pdo->commit();

            header("Location: /login.php?ok=1");
            exit;

        } catch(Exception $e){
            $pdo->rollBack();
            $erro = "Erro ao cadastrar: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Cadastro - Orçamentaria Express</title>

<!-- Open Graph -->
<meta property="og:title" content="Cadastro - Orçamentaria Express" />
<meta property="og:description" content="Criar Conta - Orçamentaria Express" />
<meta property="og:url" content="https://www.mecanica.bacuridigital.com/" />
<meta property="og:type" content="website" />

<link rel="icon" href="https://www.mecanica.bacuridigital.com/imagens/orcamentaria_express.png" type="image/png">

<meta property="og:image" content="https://www.mecanica.bacuridigital.com/imagens/orcamentaria_express.png" />
<meta property="og:image:secure_url" content="https://www.mecanica.bacuridigital.com/imagens/orcamentaria_express.png" />
<meta property="og:image:type" content="image/png" />
<meta property="og:image:width" content="500" />
<meta property="og:image:height" content="420" />

<meta property="og:locale" content="pt_BR" />

<!-- WhatsApp / compat -->
<meta name="twitter:card" content="summary_large_image" />

<!-- Tailwind -->
<script src="https://cdn.tailwindcss.com"></script>

<!-- Fonte -->
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">

<script>
tailwind.config = {
  theme: {
    extend: {
      colors: {
        primary: '#0f172a'
      }
    }
  }
}
</script>

<body class="bg-gray-900 text-gray-800 font-[Inter]">

<div class="min-h-screen flex items-center justify-center p-6">
  <div class="max-w-md w-full">

    <div class="bg-white rounded-2xl shadow-sm border p-6">

      <h2 class="text-xl font-semibold text-center mb-4">Criar Conta</h2>

      <?php if($erro): ?>
        <div class="bg-red-100 text-red-700 px-4 py-2 rounded mb-4">
            <?= htmlspecialchars($erro) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="space-y-4">

        <input name="empresa" placeholder="Empresa" class="w-full border rounded-lg px-3 py-2" required>
        <input name="nome" placeholder="Seu nome" class="w-full border rounded-lg px-3 py-2" required>
        <input name="email" type="email" placeholder="Email" class="w-full border rounded-lg px-3 py-2" required>
        <input name="senha" type="password" placeholder="Senha" class="w-full border rounded-lg px-3 py-2" required>

        <button class="w-full bg-primary text-white py-3 rounded-lg font-semibold">
          Criar conta
        </button>

        <p class="text-sm text-center mt-4">
          Já tem conta?
          <a href="/login.php" class="text-primary font-medium">Fazer login</a>
        </p>

      </form>

    </div>
  </div>
</div>

</body>
</html>