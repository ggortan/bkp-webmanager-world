<?php
/**
 * Arquivo de redirecionamento para a pasta public
 * 
 * Este arquivo redireciona automaticamente para public/index.php
 * Não requer mod_rewrite ativo
 */

// Define o caminho raiz apenas se não estiver definido
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', __DIR__);
}

// Redireciona para o arquivo público
require ROOT_PATH . '/public/index.php';
