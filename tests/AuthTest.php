<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Middleware\Auth;
use Uspdev\NfeWs\Middleware\AuthException;

class AuthTest extends TestCase
{
    private string $tempPwdFile;
    private Config $config;
    private string $originalPwdFile;

    protected function setUp(): void
    {
        $this->tempPwdFile = sys_get_temp_dir() . '/test_pwd_' . uniqid() . '.json';
        $this->config = new Config();
        $this->originalPwdFile = $this->config->pwdFile;
        $this->config->pwdFile = $this->tempPwdFile;

        unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['X_API_KEY'], $_GET['api_key'], $_GET['key']);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPwdFile)) {
            @unlink($this->tempPwdFile);
        }

        $this->config->pwdFile = $this->originalPwdFile;
        Config::resetInstance();

        unset($_SERVER['HTTP_X_API_KEY'], $_SERVER['X_API_KEY'], $_GET['api_key'], $_GET['key']);
    }

    public function testExtractApiKeyFromHttpServerHeader(): void
    {
        $_SERVER['HTTP_X_API_KEY'] = 'token-from-header';
        $this->assertSame('token-from-header', Auth::extractApiKey());
    }

    public function testExtractApiKeyFromGetParameter(): void
    {
        $_GET['api_key'] = 'token-from-get';
        $this->assertSame('token-from-get', Auth::extractApiKey());
    }

    public function testExtractApiKeyReturnsEmptyWhenNoKeyProvided(): void
    {
        $this->assertSame('', Auth::extractApiKey());
    }

    public function testAuthenticateThrowsWhenApiKeyIsEmpty(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Você deve informar uma API key válida para acessar este recurso');

        $auth = new Auth($this->config);
        $auth->authenticate('');
    }

    public function testAuthenticateThrowsWhenPwdFileDoesNotExist(): void
    {
        $this->expectException(AuthException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Este webservice ainda não foi configurado!');

        $auth = new Auth($this->config);
        $auth->authenticate('any-key');
    }

    public function testAuthenticateThrowsWhenPwdFileIsCorrupted(): void
    {
        file_put_contents($this->tempPwdFile, 'invalid json content');

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(500);
        $this->expectExceptionMessage('Formato do arquivo de credenciais inválido!');

        $auth = new Auth($this->config);
        $auth->authenticate('any-key');
    }

    public function testAuthenticateThrowsWhenKeyNotFound(): void
    {
        $keys = [
            [
                'name' => 'client1',
                'hash' => hash('sha256', 'correct-secret'),
                'created_at' => date('c'),
                'revoked_at' => null,
            ],
        ];
        file_put_contents($this->tempPwdFile, json_encode($keys));

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Credenciais inválidas');

        $auth = new Auth($this->config);
        $auth->authenticate('wrong-secret');
    }

    public function testAuthenticateThrowsWhenKeyIsRevoked(): void
    {
        $keys = [
            [
                'name' => 'revoked-client',
                'hash' => hash('sha256', 'my-secret'),
                'created_at' => date('c'),
                'revoked_at' => date('c'),
            ],
        ];
        file_put_contents($this->tempPwdFile, json_encode($keys));

        $this->expectException(AuthException::class);
        $this->expectExceptionCode(401);
        $this->expectExceptionMessage('Credenciais revogadas');

        $auth = new Auth($this->config);
        $auth->authenticate('my-secret');
    }

    public function testAuthenticateReturnsUserArrayOnSuccess(): void
    {
        $keys = [
            [
                'name' => 'valid-client',
                'hash' => hash('sha256', 'super-secret'),
                'description' => 'Sistema ERP',
                'created_at' => date('c'),
                'revoked_at' => null,
            ],
        ];
        file_put_contents($this->tempPwdFile, json_encode($keys));

        $auth = new Auth($this->config);
        $user = $auth->authenticate('super-secret');

        $this->assertIsArray($user);
        $this->assertSame('valid-client', $user['name']);
        $this->assertSame('Sistema ERP', $user['description']);
    }

    public function testHandlePassesWhenValidApiKeyAndValidDir(): void
    {
        $keys = [
            [
                'name' => 'valid-client',
                'hash' => hash('sha256', 'super-secret'),
                'created_at' => date('c'),
                'revoked_at' => null,
            ],
        ];
        file_put_contents($this->tempPwdFile, json_encode($keys));

        $_SERVER['HTTP_X_API_KEY'] = 'super-secret';
        $auth = new Auth($this->config);

        $this->assertTrue($auth->handle());
        $this->assertTrue(Auth::handle());
    }
}
