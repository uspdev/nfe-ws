<?php

// Aparecerá na resposta referente a sefaz
define('VERSAO', '2.0.8');

// Corrige SCRIPT_NAME quando executado pelo servidor embutido do PHP (php -S)
if (php_sapi_name() === 'cli-server') {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../vendor/autoload.php';

// Suprime E_DEPRECATED para compatibilidade de bibliotecas legadas (sped-da, etc.) com PHP 8.2+
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', 'On');
//Flight::set('flight.log_errors', true);

$controller = new ApiController();

// Rota pública de documentação
Flight::route('GET /', [$controller, 'index']);

// Middleware de autenticação: todas as demais rotas exigem X-API-Key (ou ?api_key=)
Flight::route('*', [$controller, 'authenticate']);

// Rotas protegidas da API
Flight::route('GET /status', [$controller, 'status']);
Flight::route('GET /@tipo:[a-z]+/@file', [$controller, 'download']);
Flight::route('POST /xml', [$controller, 'processXml']);

Flight::start();
