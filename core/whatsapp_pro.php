<?php
header('Content-Type: text/html; charset=UTF-8');

/**
 * Normaliza string para UTF-8 válido
 */
function normalizar_utf8($str){
    if ($str === null) return null;

    // Força conversão SEM confiar no detect
    return mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
}

/**
 * Limpa telefone
 */
function whatsapp_limpar($telefone){
    return preg_replace('/\D/', '', $telefone);
}

/**
 * Mensagem com emojis (HEX seguro)
 */
function whatsapp_msg_orcamento($cliente, $id, $valor, $link) {
    $valor = number_format($valor, 2, ',', '.');

    // Emojis seguros (UTF-8 HEX)
    $e_doc   = "\xF0\x9F\xA7\xBE"; // 🧾
    $e_user  = "\xF0\x9F\x91\xA4"; // 👤
    $e_money = "\xF0\x9F\x92\xB0"; // 💰
    $e_link  = "\xF0\x9F\x94\x97"; // 🔗
    $e_check = "\xE2\x9C\x94";     // ✔

    return
        "{$e_doc} *Orçamento #{$id}*\n\n" .
        "{$e_user} Cliente: {$cliente}\n" .
        "{$e_money} Valor: R$ {$valor}\n\n" .
        "{$e_link} Acesse seu orçamento:\n{$link}\n\n" .
        "{$e_check} Você pode aprovar ou recusar direto no link\n\n" .
        "Qualquer dúvida, estamos à disposição!";
}

/**
 * Gera link WhatsApp (correto)
 */
function whatsapp_link($telefone, $mensagem){
    $telefone = whatsapp_limpar($telefone);
    if(!$telefone) return null;

    $mensagem = normalizar_utf8($mensagem);

    return "https://api.whatsapp.com/send?phone=55{$telefone}&text=" . rawurlencode($mensagem);
}

/**
 * Função principal
 */
function whatsapp_envio_orcamento($telefone, $cliente, $id, $valor, $token) {
    $link = "https://www.mecanica.bacuridigital.com/publico.php?t=" . $token;

    $cliente = normalizar_utf8($cliente);

    $msg  = whatsapp_msg_orcamento($cliente, $id, $valor, $link);

    return whatsapp_link($telefone, $msg);
}