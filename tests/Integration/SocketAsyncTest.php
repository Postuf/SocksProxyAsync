<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\Constants;
use SocksProxyAsync\Proxy;
use SocksProxyAsync\SocketAsync;
use SocksProxyAsync\SocketAsyncCallback;
use SocksProxyAsync\SocksException;

use function array_values;
use function count;
use function explode;
use function sprintf;
use function strlen;
use function trim;
use function usleep;

final class SocketAsyncTest extends TestCase
{
    /** @see node subdir */
    private const HOSTNAME             = 'localhost';
    private const HOST                 = '127.0.0.1';
    private const PORT                 = 8080;
    private const PROXY                = '127.0.0.1:1080';
    private const PROXY_HOSTNAME       = 'localhost:1080';
    private const DEFAULT_DNS_FOR_TEST = '127.0.0.1:9999';

    private SocketAsync $socket;
    private Proxy $proxy;

    protected function setUp(): void
    {
        $this->proxy  = new Proxy(self::PROXY);
        $this->socket = new SocketAsync($this->proxy, self::HOST, self::PORT);
        parent::setUp();
    }

    public function testAsyncSocketIp(): void
    {
        $proxy  = new Proxy('127.0.0.1:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT,
            self::DEFAULT_DNS_FOR_TEST
        );
        self::assertEquals(self::HOST, $this->socket->getHost());

        while (! $socket->ready()) {
            $socket->poll();
        }

        self::assertEquals('127.0.0.1', $proxy->getServer());
        self::assertEquals(self::HOST, $socket->getHost());
    }

    public function testAsyncSocketIpWithDefaultDns(): void
    {
        $proxy  = new Proxy('127.0.0.1:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT
        );
        self::assertEquals(self::HOST, $this->socket->getHost());

        while (! $socket->ready()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }

        self::assertEquals('127.0.0.1', $proxy->getServer());
        self::assertEquals(self::HOST, $socket->getHost());
    }

    public function testAsyncSocketName(): void
    {
        $proxy  = new Proxy('localhost:1080');
        $socket = new SocketAsync(
            $proxy,
            self::HOST,
            self::PORT,
            Constants::DEFAULT_TIMEOUT,
            self::DEFAULT_DNS_FOR_TEST
        );
        self::assertEquals(self::HOST, $this->socket->getHost());

        while (! $socket->ready()) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $socket->poll();
        }

        self::assertEquals('127.0.0.1', $proxy->getServer());
        self::assertEquals(self::HOST, $socket->getHost());
    }

    /**
     * @throws SocksException
     */
    public function testSocketWorksWithName(): void
    {
        $this->proxy = new Proxy(self::PROXY_HOSTNAME);
        for ($i = 0; $i < 2; $i++) {
            $this->socket = new SocketAsync($this->proxy, self::HOSTNAME, self::PORT);
            self::assertEquals(self::HOSTNAME, $this->socket->getHost());

            while (! $this->socket->ready()) {
                $this->socket->poll();
            }

            // http req body
            $br   = "\r\n";
            $data = sprintf('GET /test%sHost: 127.0.0.1:8080%sAccept: identity%s%s', $br, $br, $br, $br);

            $writtenBytes = $this->socket->write($data);
            self::assertEquals($writtenBytes, strlen($data));
            usleep(2000000);
            $response = $this->socket->read(4096);
            $lines    = explode("\n", trim($response));
            foreach ($lines as $k => $line) {
                if (! $line || trim($line) !== 'Connection: close') {
                    continue;
                }

                unset($lines[$k]);
            }

            $lines    = array_values($lines);
            $lastLine = $lines[count($lines) - 1];
            /* @see node/http/test */
            self::assertEquals('test', $lastLine);
            $this->socket->stop();
            unset($this->socket);
        }
    }

    /**
     * @throws SocksException
     */
    public function testSocketWorksWithIp(): void
    {
        self::assertEquals(self::HOST, $this->socket->getHost());

        while (! $this->socket->ready()) {
            $this->socket->poll();
        }

        // http req body
        $br           = "\r\n";
        $data         = sprintf('GET /test%sHost: 127.0.0.1:8080%sAccept: identity%s%s', $br, $br, $br, $br);
        $writtenBytes = $this->socket->write($data);
        self::assertEquals($writtenBytes, strlen($data));
        usleep(2000000);
        $response = $this->socket->read(4096);
        $lines    = explode("\n", trim($response));
        foreach ($lines as $k => $line) {
            if (! $line || trim($line) !== 'Connection: close') {
                continue;
            }

            unset($lines[$k]);
        }

        $lines    = array_values($lines);
        $lastLine = $lines[count($lines) - 1];
        /* @see node/http/test */
        self::assertEquals('test', $lastLine);
        $this->socket->stop();
    }

    /**
     * @throws SocksException
     *
     * @noinspection PhpUnusedParameterInspection
     */
    public function testSocketCbWorks(): void
    {
        $ready  = false;
        $socket = new SocketAsyncCallback(
            $this->proxy,
            self::HOST,
            self::PORT,
            static function (SocketAsyncCallback $socketAsyncCallback) use (&$ready): void {
                $ready = true;
            }
        );
        self::assertEquals(self::HOST, $socket->getHost());

        while (! $ready) {
            $socket->poll();
        }

        // http req body
        $br           = "\r\n";
        $data         = sprintf('GET /test%sHost: 127.0.0.1:8080%sAccept: identity%s%s', $br, $br, $br, $br);
        $writtenBytes = $socket->write($data);
        self::assertEquals($writtenBytes, strlen($data));
        usleep(2000000);
        $response = $socket->read(4096);
        $lines    = explode("\n", trim($response));
        foreach ($lines as $k => $line) {
            if (! $line || trim($line) !== 'Connection: close') {
                continue;
            }

            unset($lines[$k]);
        }

        $lines    = array_values($lines);
        $lastLine = $lines[count($lines) - 1];
        /* @see node/http/test */
        self::assertEquals('test', $lastLine);
        $socket->stop();
    }

    /**
     * @throws SocksException
     */
    public function testThrowOnIncorrectPort(): void
    {
        // here we assume port 9999 is not occupied
        $socket = new SocketAsync($this->proxy, self::HOST, 9999);
        $this->expectException(SocksException::class);
        while (! $this->socket->ready()) {
            $socket->poll();
        }
    }
}
