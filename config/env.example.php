<?php
/**
 * Copie este arquivo para:
 * /config/env.local.php
 *
 * Preencha com os dados reais do servidor.
 * Nunca envie env.local.php para ZIP público, GitHub ou cliente final.
 */

return [
    // URL base pública do sistema, sem barra final.
    'APP_URL' => 'https://www.mecanica.bacuridigital.com',

    // Banco de dados
    'DB_HOST' => 'localhost',
    'DB_NAME' => 'nome_do_banco',
    'DB_USER' => 'usuario_do_banco',
    'DB_PASS' => 'senha_do_banco',

    // Google OAuth
    'GOOGLE_CLIENT_ID' => 'SEU_GOOGLE_CLIENT_ID',
	'GOOGLE_CLIENT_SECRET' => 'SEU_GOOGLE_CLIENT_SECRET',
    'GOOGLE_REDIRECT_URI' => 'https://www.mecanica.bacuridigital.com/login_google.php',
];