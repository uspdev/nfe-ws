<?php

use Uspdev\NfeWs\Config;

if (!function_exists('env')) {
    /**
     * Obtém o valor de uma variável de ambiente, similar ao helper do Laravel.
     *
     * @param string $key Chave da variável de ambiente
     * @param mixed $default Valor padrão caso a variável não esteja definida
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        if (class_exists(Config::class)) {
            Config::loadEnv();
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null) {
            return $default instanceof Closure ? $default() : $default;
        }

        switch (strtolower((string)$value)) {
            case 'true':
            case '(true)':
                return true;
            case 'false':
            case '(false)':
                return false;
            case 'empty':
            case '(empty)':
                return '';
            case 'null':
            case '(null)':
                return null;
        }

        if (is_string($value) && strlen($value) > 1 && str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        return $value;
    }
}

if (!function_exists('config')) {
    /**
     * Helper global para acessar configurações da aplicação
     *
     * @param string|null $key Chave de configuração (ex: 'base_url', 'cache_sefaz_ttl', 'nfe_config.cnpj')
     * @param mixed $default Valor padrão caso a chave não exista
     * @return mixed|\Uspdev\NfeWs\Config Retorna o valor da configuração ou a instância única do Config
     */
    function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return Config::getInstance();
        }
        return Config::get($key, $default);
    }
}
