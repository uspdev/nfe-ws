<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Middleware\Cors;

class CorsTest extends TestCase
{
    private array $savedServer;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER;
        unset($_SERVER['REQUEST_METHOD'], $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']);
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->savedServer;
    }

    public function testHandleDoesNotExitOnGet(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'GET';

        // Não deve dar exit
        Cors::handle();
        $this->assertTrue(true);
    }
}
