<?php

namespace Tests;

use PHPUnit\Framework\TestCase;
use Uspdev\NfeWs\Config;

class HelpersTest extends TestCase
{
    public function testEnvReturnsDefaultWhenNotSet(): void
    {
        $uniqueKey = 'NON_EXISTENT_VAR_' . uniqid();
        $this->assertNull(env($uniqueKey));
        $this->assertSame('default_value', env($uniqueKey, 'default_value'));
    }

    public function testEnvResolvesClosureDefault(): void
    {
        $uniqueKey = 'NON_EXISTENT_VAR_' . uniqid();
        $this->assertSame('closure_value', env($uniqueKey, function () {
            return 'closure_value';
        }));
    }

    public function testEnvParsesBooleansAndSpecialStrings(): void
    {
        $_ENV['TEST_TRUE'] = 'true';
        $_ENV['TEST_TRUE_PAREN'] = '(true)';
        $_ENV['TEST_FALSE'] = 'false';
        $_ENV['TEST_FALSE_PAREN'] = '(false)';
        $_ENV['TEST_EMPTY'] = 'empty';
        $_ENV['TEST_EMPTY_PAREN'] = '(empty)';
        $_ENV['TEST_NULL'] = 'null';
        $_ENV['TEST_NULL_PAREN'] = '(null)';
        $_ENV['TEST_QUOTED'] = '"quoted_text"';

        $this->assertTrue(env('TEST_TRUE'));
        $this->assertTrue(env('TEST_TRUE_PAREN'));
        $this->assertFalse(env('TEST_FALSE'));
        $this->assertFalse(env('TEST_FALSE_PAREN'));
        $this->assertSame('', env('TEST_EMPTY'));
        $this->assertSame('', env('TEST_EMPTY_PAREN'));
        $this->assertNull(env('TEST_NULL'));
        $this->assertNull(env('TEST_NULL_PAREN'));
        $this->assertSame('quoted_text', env('TEST_QUOTED'));

        unset(
            $_ENV['TEST_TRUE'],
            $_ENV['TEST_TRUE_PAREN'],
            $_ENV['TEST_FALSE'],
            $_ENV['TEST_FALSE_PAREN'],
            $_ENV['TEST_EMPTY'],
            $_ENV['TEST_EMPTY_PAREN'],
            $_ENV['TEST_NULL'],
            $_ENV['TEST_NULL_PAREN'],
            $_ENV['TEST_QUOTED']
        );
    }

    public function testConfigHelper(): void
    {
        $this->assertInstanceOf(Config::class, config());
        $this->assertSame(config()->appVersao, config('app_versao'));
    }

    public function testNewDomainClassesAreAvailable(): void
    {
        $this->assertTrue(class_exists(\Uspdev\NfeWs\Nfe::class));
        $this->assertTrue(class_exists(\Uspdev\NfeWs\Sefaz::class));
        $this->assertTrue(class_exists(\Uspdev\NfeWs\Protocolo::class));
    }
}
