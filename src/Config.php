<?php

namespace Uspdev\NfeWs;

use Dotenv\Dotenv;
use NFePHP\Common\Certificate;

class Config
{
    private static ?self $instance = null;
    private static bool $envLoaded = false;
    private ?Certificate $certificate = null;

    private array $data = [];

    public string $local;
    public string $certFile;
    public string $certPwd;
    public string $pwdFile;
    public string $baseUrl;
    public bool $debug;
    public string $appVersao;
    public int $cacheSefazTtl;
    public bool $cachePdf;
    public array $nfeConfig = [];

    public function __construct()
    {
        if (self::$instance !== null) {
            $this->copyFrom(self::$instance);
            return;
        }

        self::loadEnv();
        $this->loadConfiguration();
        self::$instance = $this;
    }

    /**
     * Garante que as variaveis do arquivo .env sejam carregadas
     */
    public static function loadEnv(): void
    {
        if (self::$envLoaded) {
            return;
        }

        $baseDir = dirname(__DIR__);
        if (class_exists(Dotenv::class) && is_file($baseDir . '/.env')) {
            $dotenv = Dotenv::createImmutable($baseDir);
            $dotenv->safeLoad();
        }

        self::$envLoaded = true;
    }

    /**
     * Retorna a instancia unica global da configuracao (Singleton)
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Reseta a instancia unica para isolamento em testes unitarios
     */
    public static function resetInstance(): void
    {
        self::$instance = null;
    }

    /**
     * Copia as propriedades da instancia ativa
     */
    private function copyFrom(self $source): void
    {
        $this->data = $source->data;
        $this->local = $source->local;
        $this->certFile = $source->certFile;
        $this->certPwd = $source->certPwd;
        $this->pwdFile = $source->pwdFile;
        $this->baseUrl = $source->baseUrl;
        $this->debug = $source->debug;
        $this->appVersao = $source->appVersao;
        $this->cacheSefazTtl = $source->cacheSefazTtl;
        $this->cachePdf = $source->cachePdf;
        $this->nfeConfig = $source->nfeConfig;
        $this->certificate = $source->certificate;
    }

    /**
     * Carrega as configuracoes a partir de variaveis de ambiente com valores padrao
     */
    private function loadConfiguration(): void
    {
        // 1. Versao da aplicacao
        $this->appVersao = (string)env('NFE_APP_VERSAO', '2.0.8');
        if (!defined('VERSAO')) {
            define('VERSAO', $this->appVersao);
        }

        // 2. Caminhos de arquivos
        $localRaw = env('NFE_LOCAL_PATH', 'local/recebidas/');
        $this->local = $this->resolvePath($localRaw);
        if ($this->local !== '' && !str_ends_with($this->local, '/')) {
            $this->local .= '/';
        }

        $certRaw = env('NFE_CERT_FILE');
        $this->certFile = $this->resolvePath($certRaw);

        $this->certPwd = (string)env('NFE_CERT_PASSWORD', '');

        $pwdRaw = env('NFE_PASSWD_FILE', 'local/passwd.json');
        $this->pwdFile = $this->resolvePath($pwdRaw);

        // 3. Rede e comportamento
        $baseRaw = env('NFE_BASE_URL', '');
        $this->baseUrl = $baseRaw !== '' ? rtrim((string)$baseRaw, '/') . '/' : '';

        $debugRaw = env('NFE_DEBUG', false);
        $this->debug = filter_var($debugRaw, FILTER_VALIDATE_BOOLEAN);

        $ttlRaw = env('NFE_CACHE_SEFAZ_TTL', 600);
        $this->cacheSefazTtl = (int)$ttlRaw;

        $pdfRaw = env('NFE_CACHE_PDF', true);
        $this->cachePdf = filter_var($pdfRaw, FILTER_VALIDATE_BOOLEAN);

        // 4. Parametros NFePHP
        $this->nfeConfig = [
            'atualizacao' => (string)env('NFE_ATUALIZACAO', '2022-07-25 10:26:00'),
            'tpAmb'       => (int)env('NFE_TP_AMB', 1),
            'razaosocial' => (string)env('NFE_RAZAO_SOCIAL'),
            'cnpj'        => (string)env('NFE_CNPJ'),
            'siglaUF'     => (string)env('NFE_SIGLA_UF'),
            'schemes'     => (string)env('NFE_SCHEMES', 'PL_010g'),
            'versao'      => (string)env('NFE_VERSAO', '4.00'),
        ];

        // Mapeia dados normalizados para consumo via get() ou helper config()
        $this->data = [
            'local'           => $this->local,
            'cert_file'       => $this->certFile,
            'cert_pwd'        => $this->certPwd,
            'pwd_file'        => $this->pwdFile,
            'base_url'        => $this->baseUrl,
            'debug'           => $this->debug,
            'app_versao'      => $this->appVersao,
            'cache_sefaz_ttl' => $this->cacheSefazTtl,
            'cache_pdf'       => $this->cachePdf,
            'nfe_config'      => $this->nfeConfig,
        ];
    }

    /**
     * Consulta um valor de configuracao por chave normalizada (ex: 'base_url', 'NFE_BASE_URL', 'nfe_config.cnpj')
     *
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        $instance = self::getInstance();

        // 1. Acesso com notacao de ponto (ex: nfe_config.cnpj)
        if (str_contains($key, '.')) {
            $parts = explode('.', $key);
            $current = $instance->data;
            foreach ($parts as $part) {
                if (is_array($current) && array_key_exists($part, $current)) {
                    $current = $current[$part];
                } else {
                    return $default;
                }
            }
            return $current;
        }

        // 2. Chave normalizada (ex: 'base_url', 'NFE_BASE_URL', 'local_path')
        $normalizedKey = strtolower(str_replace(['NFE_', '-'], ['', '_'], $key));
        if ($normalizedKey === 'local_path') {
            $normalizedKey = 'local';
        } elseif ($normalizedKey === 'cert_password') {
            $normalizedKey = 'cert_pwd';
        } elseif ($normalizedKey === 'passwd_file') {
            $normalizedKey = 'pwd_file';
        }

        if (array_key_exists($normalizedKey, $instance->data)) {
            return $instance->data[$normalizedKey];
        }

        // 3. Fallback direto no array $_ENV
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        return $default;
    }

    /**
     * Resolve caminhos relativos baseando-se na raiz do projeto
     */
    private function resolvePath(?string $path): string
    {
        if ($path === null || $path === '') {
            return '';
        }
        if (str_starts_with($path, '/') || preg_match('#^[a-zA-Z]:[/\\\\]#', $path)) {
            return $path;
        }
        return dirname(__DIR__) . '/' . ltrim($path, '/');
    }

    /**
     * Carrega e instancia o certificado digital ICP-Brasil com cache em memoria
     * e suporte a cifras legadas (OpenSSL 3+).
     *
     * @return \NFePHP\Common\Certificate
     */
    public function getCertificate(): Certificate
    {
        if ($this->certificate !== null) {
            return $this->certificate;
        }

        if (!is_file($this->certFile)) {
            throw new \RuntimeException('Certificado não encontrado em: ' . $this->certFile);
        }

        $content = file_get_contents($this->certFile);
        try {
            $this->certificate = Certificate::readPfx($content, $this->certPwd);
            return $this->certificate;
        } catch (\Throwable $e) {
            // Em ambientes OpenSSL 3+, certificados ICP-Brasil com cifras legadas (RC2/3DES)
            // falham no openssl_pkcs12_read. Fallback de conversao via openssl CLI:
            $cmd = sprintf(
                'openssl pkcs12 -in %s -passin %s -legacy -nodes 2>/dev/null | openssl pkcs12 -export -passout %s 2>/dev/null',
                escapeshellarg($this->certFile),
                escapeshellarg('pass:' . $this->certPwd),
                escapeshellarg('pass:' . $this->certPwd)
            );
            $converted = shell_exec($cmd);
            if (!empty($converted)) {
                $this->certificate = Certificate::readPfx($converted, $this->certPwd);
                return $this->certificate;
            }
            throw $e;
        }
    }
}
