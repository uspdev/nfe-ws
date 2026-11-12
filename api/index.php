<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Uspdev\NfeWs\App;
use Uspdev\NfeWs\Controllers\ApiController;
use Uspdev\NfeWs\Middleware\Auth;
use Uspdev\NfeWs\Middleware\Cors;

// Inicializa a aplicação com a configuração de ambiente e debug
$app = App::create();

// Trata CORS globalmente para todas as requisições (incluindo preflight OPTIONS)
Cors::handle();

$controller = new ApiController();

// Rota pública de documentação
$app->route('GET /', [$controller, 'index']);

// Middleware de autenticação: todas as demais rotas exigem X-API-Key (ou ?api_key=)
$app->route('*', [Auth::class, 'handle']);

// Rotas protegidas da API
$app->route('GET /status', [$controller, 'status']);
$app->route('GET /@tipo:[a-z]+/@file', [$controller, 'download']);
$app->route('POST /xml', [$controller, 'processXml']);

$app->start();
