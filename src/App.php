<?php

namespace Uspdev\NfeWs;

use flight\Engine;

/**
 * Fábrica e configurador da instância da aplicação Flight Engine.
 */
class App
{
    /**
     * Cria e inicializa a Engine do Flight com as configurações de ambiente e debug
     */
    public static function create(?Config $config = null): Engine
    {
        $config = $config ?? Config::getInstance();
        $app = new Engine();

        if ($config->debug) {
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

        return $app;
    }
}
