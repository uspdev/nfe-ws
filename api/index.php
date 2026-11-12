<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Uspdev\NfeWs\App;
use Uspdev\NfeWs\Controllers\ApiController;
use Uspdev\NfeWs\Middleware\Auth;
use Uspdev\NfeWs\Middleware\Cors;

// Inicializa a aplicação com a configuração de ambiente e debug
$app = App::create();

// Middleware CORS registrado no pipeline de rotas
$app->route('*', [Cors::class, 'handle']);

// Rota pública de documentação
$app->route('GET /', [ApiController::class, 'index']);

// Middleware de autenticação: todas as demais rotas exigem X-API-Key (ou ?api_key=)
$app->route('*', [Auth::class, 'handle']);

// Rotas protegidas da API
$app->route('GET /status', [ApiController::class, 'status']);
$app->route('GET /@tipo:[a-z]+/@file', [ApiController::class, 'download']);
$app->route('POST /xml', [ApiController::class, 'processXml']);

$app->start();
