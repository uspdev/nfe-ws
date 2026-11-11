<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Config;
use Uspdev\NfeWs\Logger;

class LoggerTest extends TestCase
{
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        unset(
            $_SERVER['HTTP_CF_CONNECTING_IP'],
            $_SERVER['HTTP_X_FORWARDED_FOR'],
            $_SERVER['HTTP_X_REAL_IP'],
            $_SERVER['HTTP_CLIENT_IP'],
            $_SERVER['REMOTE_ADDR']
        );
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testGetClientIpReturnsCliWhenNoHeaders(): void
    {
        $this->assertSame('CLI', Logger::getClientIp());
    }

    public function testGetClientIpUsesRemoteAddrWhenNoProxyHeaders(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.50';
        $this->assertSame('192.168.1.50', Logger::getClientIp());
    }

    public function testGetClientIpExtractsFromXForwardedForSingle(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.144.1.2';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';

        $this->assertSame('200.144.1.2', Logger::getClientIp());
    }

    public function testGetClientIpExtractsFirstIpFromXForwardedForMultiple(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.144.1.2, 192.168.100.13, 10.0.0.1';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';

        $this->assertSame('200.144.1.2', Logger::getClientIp());
    }

    public function testGetClientIpSkipsInvalidIpInXForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid-ip, 200.144.1.2';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';

        $this->assertSame('200.144.1.2', Logger::getClientIp());
    }

    public function testGetClientIpExtractsFromXRealIp(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = '143.107.1.5';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';

        $this->assertSame('143.107.1.5', Logger::getClientIp());
    }

    public function testGetClientIpExtractsFromCfConnectingIp(): void
    {
        $_SERVER['HTTP_CF_CONNECTING_IP'] = '143.107.254.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.5';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';

        $this->assertSame('143.107.254.1', Logger::getClientIp());
    }

    public function testLogWritesRealIpToFile(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '200.144.1.99, 192.168.100.13';
        $_SERVER['REMOTE_ADDR'] = '192.168.100.13';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['REQUEST_URI'] = '/api/status';

        Logger::log('AUTH', ['status' => 'ACCEPTED', 'name' => 'tester']);

        $logFile = Logger::getLogDir() . '/access-' . date('Y-m-d') . '.log';
        $this->assertFileExists($logFile);

        $content = file_get_contents($logFile);
        $this->assertStringContainsString('ip=200.144.1.99', $content);
        $this->assertStringContainsString('name=tester', $content);
    }
}
