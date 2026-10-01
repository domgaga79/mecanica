<?php

/**
 * Resolve empresa via subdomínio
 */
function resolverEmpresa($pdo){

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = str_replace('www.', '', $host);
    $host = explode(':', $host)[0];

    $partes = explode('.', $host);

    $dominio_base = 'mecanica.bacuridigital.com';

    if($host === $dominio_base){
        return null;
    }

    $slug = $partes[0];

    $stmt = $pdo->prepare("
        SELECT * FROM empresas 
        WHERE slug=? AND status='ativa'
        LIMIT 1
    ");
    $stmt->execute([$slug]);

    $empresa = $stmt->fetch();

    if(!$empresa){
        return null;
    }

    $_SESSION['empresa_id'] = $empresa['id'];

    return $empresa;
}