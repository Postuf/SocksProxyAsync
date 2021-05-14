<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\SocksException;

final class ProxyTest extends TestCase
{
    public function testItCreates(): void
    {
        $proxy = new Proxy('1.2.3.4:80');
        self::assertEquals(80, $proxy->getPort());
        self::assertEquals('1.2.3.4', $proxy->getServer());
        self::assertEquals(null, $proxy->getLogin());
        self::assertEquals(null, $proxy->getPassword());
    }

    public function testItSetsServer(): void
    {
        $proxy = new Proxy('1.2.3.4:80');
        $proxy->setServer('1.2.3.5');
        self::assertEquals('1.2.3.5', $proxy->getServer());
    }

    public function testItCreatesWithLoginPw(): void
    {
        $proxy = new Proxy('1.2.3.4:80|a:b');
        self::assertEquals(80, $proxy->getPort());
        self::assertEquals('1.2.3.4', $proxy->getServer());
        self::assertEquals('a', $proxy->getLogin());
        self::assertEquals('b', $proxy->getPassword());
        self::assertEquals(true, $proxy->isNeedAuth());
    }

    /**
     * @throws SocksException
     */
    public function testItThrowsIncorrectType(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('1.2.3.4:80', 10);
    }

    public function testItThrowsIncorrectPipe(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('a|b|c');
    }

    public function testItThrowsIncorrectFormat(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('a:b:c');
    }
}
