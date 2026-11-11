<?php

namespace Uspdev\NfeWs;

class Auth
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? new Config();
    }

    /**
     * Define cabecalhos CORS e encerra requisições OPTIONS (preflight).
     */
    public static function handleCors(): void
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS' || isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
            echo 'OK';
            exit;
        }
    }

    /**
     * Extrai a API Key enviada na requisição HTTP (headers ou query string).
     */
    public static function extractApiKey(): string
    {
        $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_SERVER['X_API_KEY'] ?? $_GET['api_key'] ?? $_GET['key'] ?? '';
        if ($apiKey === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $name => $value) {
                if (strcasecmp($name, 'X-API-Key') === 0) {
                    return (string) $value;
                }
            }
        }
        return (string) $apiKey;
    }

    /**
     * Valida a API Key informada contra o repositório de credenciais.
     * Retorna os dados do usuário autenticado se for válida.
     *
     * @param string $apiKey
     * @return array
     * @throws AuthException
     */
    public function authenticate(string $apiKey): array
    {
        if (trim($apiKey) === '') {
            Logger::log('AUTH', ['status' => 'REJECTED', 'name' => '-', 'message' => 'API key missing']);
            throw new AuthException("Você deve informar uma API key válida para acessar este recurso\n", 401);
        }

        if (!is_file($this->config->pwdFile)) {
            Logger::log('AUTH', ['status' => 'ERROR', 'name' => '-', 'message' => 'passwd file missing']);
            throw new AuthException('Este webservice ainda não foi configurado!', 500);
        }

        $content = file_get_contents($this->config->pwdFile);
        $keys = json_decode($content, true);
        if (!is_array($keys)) {
            Logger::log('AUTH', ['status' => 'ERROR', 'name' => '-', 'message' => 'invalid passwd json format']);
            throw new AuthException('Formato do arquivo de credenciais inválido!', 500);
        }

        $apiKeyHash = hash('sha256', $apiKey);

        foreach ($keys as $entry) {
            if (is_array($entry) && isset($entry['hash']) && hash_equals($entry['hash'], $apiKeyHash)) {
                if (!empty($entry['revoked_at'])) {
                    Logger::log('AUTH', ['status' => 'REVOKED', 'name' => $entry['name'] ?? '-', 'message' => 'API key revoked']);
                    throw new AuthException('Credenciais revogadas', 401);
                }

                if (class_exists(\Flight::class)) {
                    \Flight::set('currentUser', $entry);
                }

                Logger::log('AUTH', ['status' => 'ACCEPTED', 'name' => $entry['name'] ?? 'unknown']);

                return $entry;
            }
        }

        Logger::log('AUTH', ['status' => 'REJECTED', 'name' => '-', 'message' => 'Invalid API key']);
        throw new AuthException('Credenciais inválidas', 401);
    }
}
