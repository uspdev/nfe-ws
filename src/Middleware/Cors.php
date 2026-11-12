<?php

namespace Uspdev\NfeWs\Middleware;

class Cors
{
    /**
     * Define cabeçalhos CORS e encerra requisições OPTIONS (preflight).
     */
    public static function handle(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS' || isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            echo 'OK';
            exit;
        }
    }
}
