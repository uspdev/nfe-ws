<?php

if (!function_exists('config')) {
    /**
     * Helper global para acessar configurações da aplicação
     *
     * @param string|null $key Chave de configuração (ex: 'base_url', 'cache_sefaz_ttl', 'nfe_config.cnpj')
     * @param mixed $default Valor padrão caso a chave não exista
     * @return mixed|\Config Retorna o valor da configuração ou a instância única do Config
     */
    function config(?string $key = null, $default = null)
    {
        if ($key === null) {
            return Config::getInstance();
        }
        return Config::get($key, $default);
    }
}
