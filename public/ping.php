<?php
/**
 * Diagnóstico simples - sem dependências
 * 
 * Use este arquivo para verificar se o PHP está funcionando
 * 
 * Acesse: https://seusite.com/world/bkpmng/ping.php
 */

header('Content-Type: application/json; charset=utf-8');

echo json_encode([
    'status' => 'ok',
    'message' => 'PHP funcionando',
    'timestamp' => date('c'),
    'php_version' => PHP_VERSION,
    'server' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown'
]);
