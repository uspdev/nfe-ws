<?php

namespace Tests;

use PHPUnit\Framework\TestCase;

class ApiRoutesTest extends TestCase
{
    private static $serverProcess;
    private static $serverPipes;
    private static $serverHost = '127.0.0.1';
    private static $serverPort = 8999;
    private static $baseUrl;

    private static $pwdFileBackup;
    private static $pwdFilePath;
    private static $testApiKey = 'test-api-key-secret123';

    public static function setUpBeforeClass(): void
    {
        self::$baseUrl = sprintf('http://%s:%d', self::$serverHost, self::$serverPort);

        $config = new \Config();
        self::$pwdFilePath = $config->pwdFile;

        // Backup do arquivo de senhas atual, se existir
        if (is_file(self::$pwdFilePath)) {
            self::$pwdFileBackup = file_get_contents(self::$pwdFilePath);
        } else {
            self::$pwdFileBackup = null;
        }

        // Garante que o diretorio do pwdFile existe
        $pwdDir = dirname(self::$pwdFilePath);
        if (!is_dir($pwdDir)) {
            mkdir($pwdDir, 0775, true);
        }

        // Cria API key de teste temporaria
        $testKeys = [
            'test_ws_client' => hash('sha256', self::$testApiKey),
        ];
        file_put_contents(self::$pwdFilePath, serialize($testKeys));

        // Inicia o servidor embutido do PHP apontando para o router de testes
        $cmd = sprintf(
            'exec php -S %s:%d -t %s %s',
            self::$serverHost,
            self::$serverPort,
            escapeshellarg(__DIR__ . '/../api'),
            escapeshellarg(__DIR__ . '/router.php')
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        self::$serverProcess = proc_open($cmd, $descriptors, self::$serverPipes, __DIR__ . '/..');

        // Aguarda o servidor iniciar
        $started = false;
        for ($i = 0; $i < 20; $i++) {
            usleep(100000); // 100ms
            $fp = @fsockopen(self::$serverHost, self::$serverPort, $errno, $errstr, 0.5);
            if ($fp) {
                fclose($fp);
                $started = true;
                break;
            }
        }

        if (!$started) {
            throw new \RuntimeException('Nao foi possivel iniciar o servidor embutido do PHP para testes.');
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$serverProcess)) {
            proc_terminate(self::$serverProcess);
            proc_close(self::$serverProcess);
        }

        // Restaura arquivo original de senhas
        if (self::$pwdFileBackup !== null) {
            file_put_contents(self::$pwdFilePath, self::$pwdFileBackup);
        } elseif (is_file(self::$pwdFilePath)) {
            unlink(self::$pwdFilePath);
        }
    }

    private function makeRequest(string $method, string $path, $data = null, ?string $apiKey = null): array
    {
        $url = self::$baseUrl . $path;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        $headers = [];

        if ($apiKey !== null) {
            $headers[] = 'X-API-Key: ' . $apiKey;
        }

        if ($data !== null) {
            if (is_array($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $body = curl_exec($ch);
        $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        return [
            'status' => $statusCode,
            'body'   => $body,
            'type'   => $contentType,
        ];
    }

    /**
     * Testa GET / (documentacao, rota publica sem autenticacao)
     */
    public function testGetRootReturnsDocumentationWithoutAuth(): void
    {
        $res = $this->makeRequest('GET', '/');

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('API de serviços para NFE', $res['body']);
    }

    /**
     * Testa acesso a rota protegida sem enviar API key
     */
    public function testProtectedEndpointWithoutAuthReturns401(): void
    {
        $res = $this->makeRequest('GET', '/status');

        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('Você deve informar uma API key válida', $res['body']);
    }

    /**
     * Testa acesso a rota protegida com credenciais invalidas
     */
    public function testProtectedEndpointWithInvalidCredentialsReturns401(): void
    {
        $res = $this->makeRequest('GET', '/status', null, 'api-key-invalida');

        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('Credenciais inválidas', $res['body']);
    }

    /**
     * Testa GET /status com credenciais validas
     */
    public function testGetStatusWithValidAuth(): void
    {
        $res = $this->makeRequest('GET', '/status', null, self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('status', $res['body']);
    }

    /**
     * Testa requisicao OPTIONS (preflight CORS)
     */
    public function testOptionsPreflightReturnsOk(): void
    {
        $res = $this->makeRequest('OPTIONS', '/xml');

        $this->assertContains($res['status'], [200, 204]);
    }

    /**
     * Testa que passar diretamente o hash armazenado nao autentica (apenas a API key bruta)
     */
    public function testDirectStoredHashDoesNotAuthenticate(): void
    {
        $storedHash = hash('sha256', self::$testApiKey);
        $res = $this->makeRequest('GET', '/status', null, $storedHash);

        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('Credenciais inválidas', $res['body']);
    }

    /**
     * Testa acesso ao download sem enviar API key retornando 401
     */
    public function testDownloadWithoutApiKeyReturns401(): void
    {
        $res = $this->makeRequest('GET', '/danfe/arquivo_inexistente_123.pdf');

        $this->assertSame(401, $res['status']);
        $this->assertStringContainsString('Você deve informar uma API key válida', $res['body']);
    }

    /**
     * Testa GET /danfe/inexistente.pdf com API key autenticada retornando 404
     */
    public function testDownloadFileNotFoundReturns404(): void
    {
        $res = $this->makeRequest('GET', '/danfe/arquivo_inexistente_123.pdf', null, self::$testApiKey);

        $this->assertSame(404, $res['status']);
        $this->assertStringContainsString('Arquivo nao encontrado!', $res['body']);
    }

    /**
     * Testa GET /tipo_invalido/arquivo.pdf com API key retornando erro
     */
    public function testDownloadInvalidTypeReturnsError(): void
    {
        $res = $this->makeRequest('GET', '/invalido/arquivo.pdf', null, self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $this->assertSame('erro', $res['body']);
    }

    /**
     * Testa GET /status com API key passada na query string (?api_key=...)
     */
    public function testGetStatusWithApiKeyInQueryString(): void
    {
        $res = $this->makeRequest('GET', '/status?api_key=' . self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $this->assertStringContainsString('status', $res['body']);
    }

    /**
     * Testa POST /xml sem enviar xml ou chave
     */
    public function testPostXmlWithoutDataReturnsErrorJson(): void
    {
        $res = $this->makeRequest('POST', '/xml', [], self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $json = json_decode($res['body'], true);
        $this->assertIsArray($json);
        $this->assertSame('Erro: sem dados', $json['status']);
    }

    /**
     * Testa POST /xml com chave invalida (tamanho incorreto)
     */
    public function testPostXmlWithInvalidChaveReturnsErrorJson(): void
    {
        $res = $this->makeRequest('POST', '/xml', ['chave' => '12345'], self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $json = json_decode($res['body'], true);
        $this->assertIsArray($json);
        $this->assertStringContainsString('Chave incorreta', $json['status']);
    }

    /**
     * Testa POST /xml com XML contendo estrutura invalida
     */
    public function testPostXmlWithInvalidXmlStructure(): void
    {
        $invalidXml = '<invalido><tag>teste</tag></invalido>';
        $res = $this->makeRequest('POST', '/xml', ['xml' => $invalidXml], self::$testApiKey);

        $this->assertSame(200, $res['status']);
        $json = json_decode($res['body'], true);
        $this->assertIsArray($json);
        $this->assertStringContainsString('Erro ao processar XML', $json['status']);
    }
}
