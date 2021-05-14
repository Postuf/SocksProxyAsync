<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\Socks5Socket;
use SocksProxyAsync\SocksException;

use function sprintf;
use function strlen;

final class ProxySocketTest extends TestCase
{
    private const PROXY = '127.0.0.1:1080';
    private const HOST  = '127.0.0.1';
    private const PORT  = '8080';

    private Socks5Socket $socket;

    protected function setUp(): void
    {
        parent::setUp();
        $proxy        = new Proxy(self::PROXY);
        $this->socket = new Socks5Socket($proxy, 10);
    }

    /**
     * @throws SocksException
     */
    public function testSocketIp(): void
    {
        $this->socket->createConnected(self::HOST, (int) self::PORT);
        // http req body
        $br   = "\r\n";
        $data = sprintf('GET /test%sHost: 127.0.0.1:8080%sAccept: identity%s%s', $br, $br, $br, $br);

        $writtenBytes = $this->socket->write($data);
        self::assertEquals($writtenBytes, strlen($data));
        $this->socket->disconnect();
    }
}
