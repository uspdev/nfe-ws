<?php

namespace Tests;

use NFePHP\NFe\Tools as NfeTools;
use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Logger;
use Uspdev\NfeWs\Services\Sefaz;

class SefazTest extends TestCase
{
    private string $tempDir;
    private Config $config;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/sefaz_test_' . uniqid() . '/';
        mkdir($this->tempDir, 0775, true);

        $this->config = new Config();
        $this->config->local = $this->tempDir;
        $this->config->pwdFile = $this->tempDir . 'passwd.json';
        $this->config->cacheSefazTtl = 600;
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
            @rmdir($this->tempDir);
        }
    }

    public function testConsultarChaveThrowsOnInvalidChave(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chave de NF-e inválida');

        $mockTools = $this->createMock(NfeTools::class);
        $sefaz = new Sefaz($this->config, $mockTools);
        $sefaz->consultarChave('12345');
    }

    public function testConsultarChaveUsesCacheWhenValid(): void
    {
        $chave = '35261168995539000161550020000005341262696215';
        $xmlContent = '<retConsSitNFe><cStat>100</cStat><xMotivo>Autorizado</xMotivo></retConsSitNFe>';
        file_put_contents($this->tempDir . $chave . '-prot.xml', $xmlContent);

        $mockTools = $this->createMock(NfeTools::class);
        $mockTools->expects($this->never())->method('sefazConsultaChave');

        $sefaz = new Sefaz($this->config, $mockTools);
        $resultado = $sefaz->consultarChave($chave);

        $this->assertSame($xmlContent, $resultado['raw']);
        $this->assertSame($chave, $resultado['chave']);

        $logFile = Logger::getLogDir() . '/access-' . date('Y-m-d') . '.log';
        if (file_exists($logFile)) {
            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString('[SEFAZ] [CACHE]', $logContent);
        }
    }

    public function testConsultarChaveOnlineAndWritesLog(): void
    {
        $chave = '35261168995539000161550020000005341262696215';
        $xmlContent = '<retConsSitNFe><cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo></retConsSitNFe>';

        $mockTools = $this->createMock(NfeTools::class);
        $mockTools->expects($this->once())
            ->method('sefazConsultaChave')
            ->with($chave)
            ->willReturn($xmlContent);

        $sefaz = new Sefaz($this->config, $mockTools);
        $resultado = $sefaz->consultarChave($chave);

        $this->assertSame($xmlContent, $resultado['raw']);
        $this->assertFileExists($this->tempDir . $chave . '-prot.xml');

        $logFile = Logger::getLogDir() . '/access-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $logContent = file_get_contents($logFile);
        $this->assertStringContainsString('[SEFAZ] [ONLINE]', $logContent);
        $this->assertStringContainsString('cStat=100', $logContent);
        $this->assertStringContainsString('Autorizado o uso da NF-e', $logContent);
    }

    public function testConsultarChaveOnlineErrorWritesErrorLog(): void
    {
        $chave = '35261168995539000161550020000005341262696215';

        $mockTools = $this->createMock(NfeTools::class);
        $mockTools->expects($this->once())
            ->method('sefazConsultaChave')
            ->with($chave)
            ->willThrowException(new \RuntimeException('Connection timed out'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Connection timed out');

        $sefaz = new Sefaz($this->config, $mockTools);

        try {
            $sefaz->consultarChave($chave);
        } finally {
            $logFile = Logger::getLogDir() . '/access-' . date('Y-m-d') . '.log';
            $this->assertFileExists($logFile);

            $logContent = file_get_contents($logFile);
            $this->assertStringContainsString('[SEFAZ] [ERROR]', $logContent);
            $this->assertStringContainsString('Connection timed out', $logContent);
        }
    }
}
