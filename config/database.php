<?php
/**
 * Configurações de Banco de Dados
 * 
 * Carrega a configuração do arquivo central config.php
 */

$config = require __DIR__ . '/config.php';
return $config['database'];
