<?php
/**
 * Normaliza para UTF-8 (força conversão real)
 */
function normalizar_utf8($str){
    if ($str === null) return null;

    // remove caracteres inválidos + converte
    $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8, ISO-8859-1, WINDOWS-1252');
    return iconv('UTF-8', 'UTF-8//IGNORE', $str);
}

/**
 * LIMPA TELEFONE (Brasil)
 */
function whatsapp_limpar($telefone){
    return preg_replace('/\D/', '', $telefone);
}

/**
 * GERA LINK WHATSAPP (100% seguro)
 */
function whatsapp_link($telefone, $mensagem)
{
    $telefone = whatsapp_limpar($telefone);

    if (empty($telefone)) {
        return null;
    }

    $mensagem = normalizar_utf8($mensagem);

    return "https://api.whatsapp.com/send?phone=55{$telefone}&text=" . rawurlencode($mensagem);
}

/**
 * MENSAGEM PADRÃO DE ORÇAMENTO (EMOJI SEGURO)
 */
function whatsapp_mensagem_orcamento($cliente, $id, $valor, $token)
{
    $valor = number_format($valor, 2, ',', '.');

    $baseUrl = "https://www.mecanica.bacuridigital.com/publico.php?t=" . $token;

    // Emojis em UTF-8 HEX (blindado contra encoding do arquivo)
    $e_doc   = "\xF0\x9F\xA7\xBE"; // 🧾
    $e_user  = "\xF0\x9F\x91\xA4"; // 👤
    $e_money = "\xF0\x9F\x92\xB0"; // 💰
    $e_link  = "\xF0\x9F\x94\x97"; // 🔗
    $e_check = "\xE2\x9C\x94";     // ✔

    $cliente = normalizar_utf8($cliente);

    $msg  = "{$e_doc} *Orçamento #{$id}*\n\n";
    $msg .= "{$e_user} Cliente: {$cliente}\n";
    $msg .= "{$e_money} Valor: R$ {$valor}\n\n";
    $msg .= "{$e_link} Acesse seu orçamento:\n{$baseUrl}\n\n";
    $msg .= "{$e_check} Você pode aprovar ou recusar diretamente no link\n\n";
    $msg .= "Qualquer dúvida, estamos à disposição!";

    return $msg;
}

/**
 * ATALHO COMPLETO (gera URL final)
 */
function whatsapp_orcamento($telefone, $cliente, $id, $valor, $token)
{
    if (empty($telefone)) {
        return null;
    }

    $msg = whatsapp_mensagem_orcamento($cliente, $id, $valor, $token);

    return whatsapp_link($telefone, $msg);
}