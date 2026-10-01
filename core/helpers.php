<?php

function statusFlow(){
    return [
        'rascunho',
        'enviado',
        'visualizado',
        'aprovado',
        'recusado'
    ];
}

function ativo($sql) {
    return $sql . " AND deleted_at IS NULL";
}