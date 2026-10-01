<?php
/**
 * Helper simples para leitura de variáveis de ambiente/configuração local.
 *
 * Ordem de prioridade:
 * 1. Variável de ambiente do servidor, ex.: DB_HOST
 * 2. Arquivo config/env.local.php, retornando array associativo
 * 3. Valor padrão informado na chamada
 */

if (!function_exists('app_config')) {
    function app_config(string $key, $default = null) {
        static $local = null;

        if ($local === null) {
            $local = [];
            $localFile = __DIR__ . '/env.local.php';

            if (is_file($localFile)) {
                $loaded = require $localFile;
                if (is_array($loaded)) {
                    $local = $loaded;
                }
            }
        }

        $value = getenv($key);

        if ($value !== false && $value !== '') {
            return $value;
        }

        if (array_key_exists($key, $_ENV) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $local) && $local[$key] !== '') {
            return $local[$key];
        }

        return $default;
    }
}

if (!function_exists('app_config_required')) {
    function app_config_required(string $key): string {
        $value = app_config($key);

        if ($value === null || $value === '') {
            throw new RuntimeException("Configuração obrigatória ausente: {$key}");
        }

        return (string)$value;
    }
}

if (!function_exists('app_base_url')) {
    function app_base_url(): string {
        $configured = rtrim((string)app_config('APP_URL', ''), '/');

        if ($configured !== '') {
            return $configured;
        }

        $https = (
            (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        );

        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }
}
