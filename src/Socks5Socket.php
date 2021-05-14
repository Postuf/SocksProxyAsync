<?php

declare(strict_types=1);

namespace SocksProxyAsync;

use function chr;
use function ip2long;
use function ord;
use function pack;
use function preg_match;
use function socket_connect;
use function socket_create;
use function socket_read;
use function socket_set_option;
use function socket_shutdown;
use function socket_write;
use function unpack;
use function socket_close;
use function strlen;
use function trim;

use const AF_INET;
use const SO_RCVTIMEO;
use const SO_SNDTIMEO;
use const SOCK_STREAM;
use const SOL_SOCKET;
use const SOL_TCP;

/**
 * Class which manages native socket as socks5-connected socket
 * This class works only with SOCKS v5, supports only basic
 * authorization - without login:password.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class Socks5Socket
{
    /**
     * Native socket.
     *
     * @var resource|null
     */
    protected $socksSocket;
    /**
     * Domain name, not IP address.
     */
    protected string $host;
    protected int $port;
    protected Proxy $proxy;
    protected int $timeoutSeconds;

    public function __construct(Proxy $proxy, int $timeOutSeconds)
    {
        $this->proxy          = $proxy;
        $this->timeoutSeconds = $timeOutSeconds;
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Closes active connection.
     */
    public function disconnect(): void
    {
        if ($this->socksSocket !== null) {
            @socket_shutdown($this->socksSocket, 2);
            @socket_close($this->socksSocket);
        }

        $this->socksSocket = null;
    }

    /**
     * @param string $host containing domain name
     *
     * @return resource|null
     *
     * @throws SocksException
     *
     * @noinspection PhpUnused
     */
    public function createConnected(string $host, int $port)
    {
        $this->host = $host;
        $this->port = $port;

        $this->createSocket();
        $this->connectSocket();
        $this->writeSocksGreeting();
        $socksGreetingConfig = $this->readSocksGreeting();
        $this->checkServerGreetedClient($socksGreetingConfig);
        if ($this->checkGreetingWithAuth($socksGreetingConfig)) {
            $this->writeSocksAuth();
            /** @noinspection UnusedFunctionResultInspection */
            $this->readSocksAuthStatus();
        }

        $this->connectSocksSocket();
        $this->readSocksConnectStatus();

        return $this->socksSocket;
    }

    /** @noinspection SpellCheckingInspection */
    protected function createSocket(): void
    {
        $this->socksSocket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec'  => Constants::SOCKET_CONNECT_TIMEOUT_SEC,
            'usec' => 0,
        ]);
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec'  => Constants::SOCKET_CONNECT_TIMEOUT_SEC,
            'usec' => 0,
        ]);
    }

    /**
     * @throws SocksException
     */
    protected function connectSocket(): bool
    {
        if ($this->socksSocket !== false) {
            $result = @socket_connect($this->socksSocket, $this->proxy->getServer(), $this->proxy->getPort());
            if (!$result) {
                throw new SocksException(SocksException::UNREACHABLE_PROXY, 'on connect: ');
            }
        }

        return false;
    }

    protected function writeSocksGreeting(): void
    {
        $helloMsg = "\x05\x02\x00\x02";
        $this->write($helloMsg);
    }

    protected function readSocksGreeting(): string
    {
        return $this->read(2);
    }

    /**
     * @throws SocksException
     */
    protected function checkServerGreetedClient(string $socksGreetingConfig): void
    {
        if (! $socksGreetingConfig) {
            throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED);
        }

        $socksVersion  = ord($socksGreetingConfig[0]);
        $socksAuthType = ord($socksGreetingConfig[1]);

        if ($socksVersion !== 0x05) {
            throw new SocksException(SocksException::UNEXPECTED_PROTOCOL_VERSION, (string) $socksVersion);
        }

        if ($socksAuthType !== 0x00 && $socksAuthType !== 0x02) {
            throw new SocksException(SocksException::UNSUPPORTED_AUTH_TYPE, (string) $socksAuthType);
        }
    }

    protected function checkGreetingWithAuth(string $socksGreetingConfig): bool
    {
        $socksAuthType = ord($socksGreetingConfig[1]);

        return $socksAuthType === 0x02;
    }

    protected function writeSocksAuth(): void
    {
        $userName   = $this->proxy->getLogin();
        $userLength = $userName === null ? chr(0) : chr(strlen($userName));

        $password       = $this->proxy->getPassword();
        $passwordLength = $password === null ? chr(0) : chr(strlen($password));

        $this->write("\x01" . $userLength . (string) $userName . $passwordLength . (string) $password);
    }

    /**
     * @throws SocksException
     */
    protected function readSocksAuthStatus(): bool
    {
        $socksAuthStatus = $this->read(2);

        if (!$socksAuthStatus) {
            return false;
        }

        /** @noinspection TypeUnsafeComparisonInspection */
        if ($socksAuthStatus[0] !== "\x01" || $socksAuthStatus[1] !== "\x00") {
            throw new SocksException(SocksException::AUTH_FAILED);
        }

        return true;
    }

    /**
     * SOCKS protocol: https://ru.wikipedia.org/wiki/SOCKS
     * Client`s second request and server`s second response.
     *
     * @see https://tools.ietf.org/html/rfc1928
     */
    protected function connectSocksSocket(): void
    {
        $host              = trim($this->host);
        $port              = $this->port;
        $hostnameLenBinary = chr(strlen($host));
        $portBinary        = unpack('C*', pack('L', $port));
        $portBinary        = chr((int) $portBinary[2]) . chr((int) $portBinary[1]);

        // client connection request
        $isIpV4           = preg_match('/^\d+\.\d+\.\d+\.\d+$/', $host);
        $typeByte         = $isIpV4 ? "\x01" : "\x03";
        $hostInfo         = $isIpV4
            ? pack('N', ip2long($host))
            : $hostnameLenBinary . $host;
        $establishmentMsg = "\x05\x01\x00" . $typeByte . $hostInfo . $portBinary;
        $this->write($establishmentMsg);
    }

    /**
     * @throws SocksException
     */
    protected function readSocksConnectStatus(): bool
    {
        // server connection response
        $connectionStatus = $this->read(1024);
        if (!$connectionStatus) {
            return false;
        }

        $this->checkConnectionEstablished($connectionStatus);

        return true;
    }

    /**
     * @throws SocksException
     */
    protected function checkConnectionEstablished(string $serverConnectionResponse): void
    {
        $socksVersion = ord($serverConnectionResponse[0]);
        $responseCode = ord($serverConnectionResponse[1]);

        if ($socksVersion !== 0x05) {
            throw new SocksException(SocksException::UNEXPECTED_PROTOCOL_VERSION, (string) $socksVersion);
        }

        if ($responseCode !== 0x00) {
            throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED, (string) $responseCode);
        }
    }

    /**
     * @param string $data binary to write
     *
     */
    public function write(string $data): int
    {
        /** @psalm-suppress PossiblyNullArgument */
        return @socket_write($this->socksSocket, $data);
    }

    /**
     * @param int $bytesCount
     *
     * @return string
     */
    public function read(int $bytesCount): string
    {
        /** @psalm-suppress PossiblyNullArgument */
        return @socket_read($this->socksSocket, $bytesCount);
    }

    public function getHost(): string
    {
        return $this->host;
    }
}
