<?php

declare(strict_types=1);

namespace Integration;

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
    /** @var Proxy */
    private $proxy;

    protected function setUp(): void
    {
        $this->proxy = new Proxy(static::PROXY);
        $this->socket = new SocketAsync($this->proxy, self::HOST, self::PORT);
        parent::setUp();
    }

    /** @test
     * @throws SocksException
     */
    public function test_socket_works(): void
    {
        $this->assertEquals(self::HOST, $this->socket->getHost());

        while (!$this->socket->ready()) {
            $this->socket->poll();
        }

        // http req body
        $br = "\r\n";
        $data = "GET /test{$br}Host: 127.0.0.1:8080{$br}Accept: identity{$br}{$br}";

        $writtenBytes = $this->socket->write($data);
        $this->assertEquals($writtenBytes, strlen($data));
        usleep(2000000);
        $response = $this->socket->read(4096);
        $lines = explode("\n", trim($response));
        foreach ($lines as $k => $line) {
            if ($line && trim($line) === 'Connection: close') {
                unset($lines[$k]);
            }
        }
        $lines = array_values($lines);
        $lastLine = $lines[count($lines) - 1];
        /* @see node/http/test */
        $this->assertEquals('test', $lastLine);
        $this->socket->stop();
    }

    /**
     * @throws SocksException
     */
    public function test_throw_on_incorrect_port(): void
    {
        // here we assume port 9999 is not occupied
        $socket = new SocketAsync($this->proxy, self::HOST, 9999);
        $this->expectException(SocksException::class);
        while (!$this->socket->ready()) {
            $socket->poll();
        }
    }
}
