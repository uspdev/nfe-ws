<?php

namespace Uspdev\NfeWs\Middleware;

use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Logger;
use Uspdev\NfeWs\Models\ApiKey;

class Auth
{
    private static ?self $activeInstance = null;

    private Config $config;
    private ApiKey $apiKeyModel;

    public function __construct(mixed $config = null, ?ApiKey $apiKeyModel = null)
    {
        $this->config = ($config instanceof Config) ? $config : Config::getInstance();
        $this->apiKeyModel = $apiKeyModel ?? new ApiKey($this->config);
        self::$activeInstance = $this;
    }

    /**
     * Intercepta a requisição HTTP (Middleware do Flight de forma estática)
     * Pode ser passado diretamente como callback: [Auth::class, 'handle']
     */
    public static function handle(): bool
    {
        $instance = self::$activeInstance ?? new self();
        return $instance->execute();
    }

    /**
     * Executa a interceptação, autenticação e validação do ambiente
     */
    public function execute(): bool
    {
        $apiKey = self::extractApiKey();

        try {
            $this->authenticate($apiKey);
        } catch (AuthException $e) {
            http_response_code($e->getCode());
            echo $e->getMessage();
            exit();
        }

        if (!is_dir($this->config->local)) {
            http_response_code(500);
            echo 'A pasta de dados não está configurada!';
            exit();
        }

        return true;
    }

    /**
     * Valida a API Key contra o model ApiKey, gerando logs de auditoria e registrando a sessão
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

        try {
            $entry = $this->apiKeyModel->authenticate($apiKey);

            if (class_exists(\Flight::class)) {
                \Flight::set('currentUser', $entry);
            }

            Logger::log('AUTH', ['status' => 'ACCEPTED', 'name' => $entry['name'] ?? 'unknown']);

            return $entry;
        } catch (\Throwable $e) {
            $msg = $e->getMessage();

            if (str_contains($msg, 'não foi configurado')) {
                Logger::log('AUTH', ['status' => 'ERROR', 'name' => '-', 'message' => 'passwd file missing']);
            } elseif (str_contains($msg, 'Formato do arquivo')) {
                Logger::log('AUTH', ['status' => 'ERROR', 'name' => '-', 'message' => 'invalid passwd json format']);
            } elseif (str_contains($msg, 'revogadas')) {
                $revokedEntry = $this->apiKeyModel->findByPlainKey($apiKey);
                $name = $revokedEntry['name'] ?? '-';
                Logger::log('AUTH', ['status' => 'REVOKED', 'name' => $name, 'message' => 'API key revoked']);
            } else {
                Logger::log('AUTH', ['status' => 'REJECTED', 'name' => '-', 'message' => 'Invalid API key']);
            }

            throw new AuthException($e->getMessage(), $e->getCode() ?: 401);
        }
    }

    /**
     * Extrai a API Key enviada na requisição HTTP (headers ou query string)
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
     * @deprecated Utilize Cors::handle()
     */
    public static function handleCors(): void
    {
        Cors::handle();
    }
}
