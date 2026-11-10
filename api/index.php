<?php

use flight\Engine;

require_once __DIR__ . '/../vendor/autoload.php';

$app = new Engine();

$debug = (bool) config('debug');

if ($debug) {
    error_reporting(E_ALL);
    ini_set('display_errors', 'On');
    $app->set('flight.debug', true);
    $app->set('flight.log_errors', true);
} else {
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
    ini_set('display_errors', 'Off');
    ini_set('log_errors', 'On');
    $app->set('flight.debug', false);
}

$controller = new ApiController();

// Rota pública de documentação
$app->route('GET /', [$controller, 'index']);

// Middleware de autenticação: todas as demais rotas exigem X-API-Key (ou ?api_key=)
$app->route('*', [$controller, 'authenticate']);

// Rotas protegidas da API
$app->route('GET /status', [$controller, 'status']);
$app->route('GET /@tipo:[a-z]+/@file', [$controller, 'download']);
$app->route('POST /xml', [$controller, 'processXml']);

$app->start();
