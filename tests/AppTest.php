<?php

namespace Tests;

use flight\Engine;
use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\App;
use Uspdev\NfeWs\Config;

class AppTest extends TestCase
{
    public function testCreateReturnsConfiguredFlightEngine(): void
    {
        $config = new Config();
        $config->debug = true;

        $app = App::create($config);

        $this->assertInstanceOf(Engine::class, $app);
        $this->assertTrue($app->get('flight.debug'));
        $this->assertTrue($app->get('flight.log_errors'));
    }

    public function testCreateWithDebugDisabled(): void
    {
        $config = new Config();
        $config->debug = false;

        $app = App::create($config);

        $this->assertInstanceOf(Engine::class, $app);
        $this->assertFalse($app->get('flight.debug'));
    }
}
