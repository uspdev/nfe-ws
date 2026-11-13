<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Uspdev\NfeWs\App;
use Uspdev\NfeWs\Controllers\ApiController;
use Uspdev\NfeWs\Controllers\V1\ApiController as V1ApiController;
use Uspdev\NfeWs\Middleware\Auth;
use Uspdev\NfeWs\Middleware\Cors;

// Inicializa a aplicação com a configuração de ambiente e debug
$app = App::create();

// Middleware CORS registrado no pipeline de rotas
$app->route('*', [Cors::class, 'handle']);

// Rota pública de documentação
$app->route('GET /', [ApiController::class, 'index']);

// Rotas de status: dados da API são públicos; dados do certificado exigem API Key
$app->route('GET /v1/status', [V1ApiController::class, 'status']);
$app->route('GET /status', [ApiController::class, 'status']);

// Middleware de autenticação: todas as demais rotas exigem X-API-Key (ou ?api_key=)
$app->route('*', [Auth::class, 'handle']);

// Rotas protegidas da API v1 (/v1/...)
$app->group('/v1', static function () use ($app): void {
    $app->route('GET /@tipo:[a-z]+/@file', [V1ApiController::class, 'download']);
    $app->route('POST /xml', [V1ApiController::class, 'processXml']);
});

$app->start();
