<?php

declare(strict_types=1);

namespace unit;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\SocksException;

class ProxyTest extends TestCase
{
    public function test_it_creates(): void
    {
        $proxy = new Proxy('1.2.3.4:80');
        self::assertEquals(80, $proxy->getPort());
        self::assertEquals('1.2.3.4', $proxy->getServer());
        self::assertEquals(null, $proxy->getLogin());
        self::assertEquals(null, $proxy->getPassword());
    }

    public function test_it_sets_server(): void
    {
        $proxy = new Proxy('1.2.3.4:80');
        $proxy->setServer('1.2.3.5');
        self::assertEquals('1.2.3.5', $proxy->getServer());
    }

    public function test_it_creates_with_login_pw(): void
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
    public function test_it_throws_incorrect_type(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('1.2.3.4:80', 10);
    }

    public function test_it_throws_incorrect_pipe(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('a|b|c');
    }

    public function test_it_throws_incorrect_format(): void
    {
        $this->expectException(SocksException::class);
        new Proxy('a:b:c');
    }
}
