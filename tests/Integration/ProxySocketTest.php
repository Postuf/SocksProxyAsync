<?php

declare(strict_types=1);

namespace Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\Socks5Socket;
use SocksProxyAsync\SocksException;

class ProxySocketTest extends TestCase
{
    private const PROXY = '127.0.0.1:1080';
    private const HOST = '127.0.0.1';
    private const PORT = '8080';

    /** @var Socks5Socket */
    private $socket;

    protected function setUp(): void
    {
        parent::setUp();
        $proxy = new Proxy(static::PROXY);
        $this->socket = new Socks5Socket($proxy, 10);
    }

    /**
     * @throws SocksException
     */
    public function test_socket_ip(): void
    {
        /** @noinspection UnusedFunctionResultInspection */
        $this->socket->createConnected(self::HOST, self::PORT);
        // http req body
        $br = "\r\n";
        $data = "GET /test{$br}Host: 127.0.0.1:8080{$br}Accept: identity{$br}{$br}";

        $writtenBytes = $this->socket->write($data);
        self::assertEquals($writtenBytes, strlen($data));
        $this->socket->disconnect();
    }
}
