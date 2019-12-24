<?php

namespace Unit;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\SocketAsync;
use SocksProxyAsync\SocksException;

class SocketAsyncTest extends TestCase
{
    /** @see node subdir */
    const HOST = '127.0.0.1';
    const PORT = '8080';
    const PROXY = '127.0.0.1:1080';

    /** @var SocketAsync */
    private $socket;

    protected function setUp(): void
    {
        $proxy = new Proxy(static::PROXY);
        $this->socket = new SocketAsync($proxy, self::HOST, self::PORT);
        parent::setUp();
    }

    /** @test
     * @throws SocksException
     */
    public function test_socket() {
        $this->assertEquals(self::HOST, $this->socket->getHost());

        while(!$this->socket->ready()) {
            $this->socket->poll();
        }

        $br = "\r\n";
        $data = "GET /test{$br}Host: 127.0.0.1:8080{$br}Accept: identity{$br}{$br}";
        $writtenBytes = $this->socket->write($data);
        $this->assertEquals($writtenBytes, strlen($data));
        usleep(2000000);
        $response = $this->socket->read(4096);
        $lines = explode("\n", trim($response));
        $lastLine = $lines[count($lines) - 1];
        $this->assertEquals('test', $lastLine);
        $this->socket->stop();
    }
}