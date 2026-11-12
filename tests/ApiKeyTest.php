<?php

namespace Tests;

use DomainException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Models\ApiKey;

class ApiKeyTest extends TestCase
{
    private string $tempPwdFile;
    private Config $config;

    protected function setUp(): void
    {
        $this->tempPwdFile = sys_get_temp_dir() . '/test_api_keys_' . uniqid() . '.json';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempPwdFile)) {
            @unlink($this->tempPwdFile);
        }
    }

    private function getModel(): ApiKey
    {
        return new ApiKey(null, $this->tempPwdFile);
    }

    public function testCreateApiKeySuccess(): void
    {
        $model = $this->getModel();
        $res = $model->create('sistema-delos', 'Integração de Compras');

        $this->assertSame('sistema-delos', $res['entry']['name']);
        $this->assertSame('Integração de Compras', $res['entry']['description']);
        $this->assertSame(64, strlen($res['plainKey']));
        $this->assertSame(hash('sha256', $res['plainKey']), $res['entry']['hash']);
        $this->assertNull($res['entry']['revoked_at']);
        $this->assertFileExists($this->tempPwdFile);

        // Recarrega de disco
        $reloaded = $this->getModel();
        $this->assertTrue($reloaded->exists('sistema-delos'));
        $this->assertTrue($reloaded->exists('SISTEMA-DELOS'));
    }

    public function testCreateThrowsOnEmptyName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('não pode ser vazio');

        $model = $this->getModel();
        $model->create('   ');
    }

    public function testCreateThrowsOnDuplicateName(): void
    {
        $model = $this->getModel();
        $model->create('meu-cliente');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Já existe uma chave cadastrada com o nome 'meu-cliente'");

        $model->create('meu-cliente');
    }

    public function testFindByPlainKey(): void
    {
        $model = $this->getModel();
        $created = $model->create('cliente-teste');

        $found = $model->findByPlainKey($created['plainKey']);
        $this->assertNotNull($found);
        $this->assertSame('cliente-teste', $found['name']);

        $this->assertNull($model->findByPlainKey('chave-incorreta'));
        $this->assertNull($model->findByPlainKey(''));
    }

    public function testRevokeApiKey(): void
    {
        $model = $this->getModel();
        $model->create('cliente-revogar');

        $revoked = $model->revoke('cliente-revogar');
        $this->assertNotNull($revoked['revoked_at']);

        // Recarrega de disco
        $reloaded = $this->getModel();
        $entry = $reloaded->findByName('cliente-revogar');
        $this->assertNotNull($entry['revoked_at']);
    }

    public function testRevokeThrowsWhenNotFound(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Nenhuma chave encontrada');

        $model = $this->getModel();
        $model->revoke('cliente-inexistente');
    }

    public function testDeleteApiKey(): void
    {
        $model = $this->getModel();
        $model->create('cliente-deletar');
        $this->assertTrue($model->exists('cliente-deletar'));

        $this->assertTrue($model->delete('cliente-deletar'));
        $this->assertFalse($model->exists('cliente-deletar'));
        $this->assertFalse($model->delete('cliente-deletar'));
    }

    public function testAllReturnsListOfKeys(): void
    {
        $model = $this->getModel();
        $this->assertCount(0, $model->all());

        $model->create('c1');
        $model->create('c2');

        $this->assertCount(2, $model->all());
    }

    public function testValidateStorageThrowsWhenMissing(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Este webservice ainda não foi configurado!');

        $model = $this->getModel();
        $model->validateStorage();
    }

    public function testValidateStorageThrowsWhenCorrupted(): void
    {
        file_put_contents($this->tempPwdFile, 'invalid-json');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Formato do arquivo de credenciais inválido!');

        $model = $this->getModel();
        $model->validateStorage();
    }

    public function testAuthenticateSuccess(): void
    {
        $model = $this->getModel();
        $created = $model->create('app-client', 'App Desc');

        $entry = $model->authenticate($created['plainKey']);
        $this->assertSame('app-client', $entry['name']);
        $this->assertFalse($model->isRevoked($entry));
    }

    public function testAuthenticateThrowsWhenRevoked(): void
    {
        $model = $this->getModel();
        $created = $model->create('revoked-client');
        $model->revoke('revoked-client');

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Credenciais revogadas');

        $model->authenticate($created['plainKey']);
    }
}
