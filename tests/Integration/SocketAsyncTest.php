<?php

declare(strict_types=1);

namespace Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Constants;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\SocketAsync;
use SocksProxyAsync\SocketAsyncCallback;
use SocksProxyAsync\SocksException;

class SocketAsyncTest extends TestCase
{
    /** @see node subdir */
    private const HOST = '127.0.0.1';
    private const HOSTNAME = 'localhost';
    private const PORT = '8080';
    private const PROXY = '127.0.0.1:1080';
    private const PROXY_HOSTNAME = 'localhost:1080';
    private const DEFAULT_DNS_FOR_TEST = '127.0.0.1:9999';

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

    /**
     * @test
     */
    public function test_async_socket_ip(): void
    {
        $proxy = new Proxy('127.0.0.1:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT,
            self::DEFAULT_DNS_FOR_TEST
        );
        $this->assertEquals(self::HOST, $this->socket->getHost());

        while (!$socket->ready()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }

        $this->assertEquals('127.0.0.1', $proxy->getServer());
        $this->assertEquals(self::HOST, $socket->getHost());
    }

    /**
     * @test
     */
    public function test_async_socket_ip_with_default_dns(): void
    {
        $proxy = new Proxy('127.0.0.1:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT
        );
        $this->assertEquals(self::HOST, $this->socket->getHost());

        while (!$socket->ready()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }

        $this->assertEquals('127.0.0.1', $proxy->getServer());
        $this->assertEquals(self::HOST, $socket->getHost());
    }

    /**
     * @test
     */
    public function test_async_socket_name(): void
    {
        $proxy = new Proxy('localhost:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT,
            self::DEFAULT_DNS_FOR_TEST
        );
        $this->assertEquals(self::HOST, $this->socket->getHost());

        while (!$socket->ready()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }

        $this->assertEquals('127.0.0.1', $proxy->getServer());
        $this->assertEquals(self::HOST, $socket->getHost());
    }

    /**
     * @test
     *
     * @throws SocksException
     */
    public function test_socket_works_with_name(): void
    {
        $this->proxy = new Proxy(static::PROXY_HOSTNAME);
        $this->socket = new SocketAsync($this->proxy, self::HOSTNAME, self::PORT);
        $this->assertEquals(self::HOSTNAME, $this->socket->getHost());

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
     * @test
     *
     * @throws SocksException
     */
    public function test_socket_works_with_ip(): void
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
     * @test
     *
     * @throws SocksException
     * @noinspection PhpUnusedParameterInspection
     */
    public function test_socket_cb_works(): void
    {
        $ready = false;
        $socket = new SocketAsyncCallback(
            $this->proxy,
            self::HOST,
            self::PORT,
            function (SocketAsyncCallback $socketAsyncCallback) use (&$ready) {
                $ready = true;
            }
        );
        $this->assertEquals(self::HOST, $socket->getHost());

        while (!$ready) {
            $socket->poll();
        }

        // http req body
        $br = "\r\n";
        $data = "GET /test{$br}Host: 127.0.0.1:8080{$br}Accept: identity{$br}{$br}";

        $writtenBytes = $socket->write($data);
        $this->assertEquals($writtenBytes, strlen($data));
        usleep(2000000);
        $response = $socket->read(4096);
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
        $socket->stop();
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
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }
    }
}
