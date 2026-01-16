<?php
/**
 * Configurações Gerais da Aplicação
 * 
 * Carrega a configuração do arquivo central config.php
 */

$config = require __DIR__ . '/config.php';
return $config['app'] + ['session' => $config['session']];
