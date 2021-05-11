<?php

declare(strict_types=1);

namespace SocksProxyAsync;

use function count;
use function explode;
use function strpos;
use function trim;

final class Proxy
{
    public const TYPE_HTTP   = 1;
    public const TYPE_SOCKS5 = 3;

    private string $server;
    private string $port;
    private int $type;

    private ?string $password = null;
    private ?string $login    = null;

    /**
     * Proxy formats:
     *      1) host:port
     *      2) host:port|login:password
     * are supported.
     *
     * @throws SocksException
     */
    public function __construct(string $serverAndPort, int $type = self::TYPE_SOCKS5)
    {
        if (strpos($serverAndPort, '|') !== false) {
            $parts = explode('|', $serverAndPort);
            if (count($parts) !== 2) {
                throw new SocksException(SocksException::PROXY_BAD_FORMAT);
            }

            $serverAndPort = $parts[0];
            $auth          = explode(':', $parts[1]);
            if (count($auth) !== 2) {
                throw new SocksException(SocksException::PROXY_BAD_FORMAT);
            }

            $this->setLoginPassword($auth[0], $auth[1]);
        }

        $proxyPath = explode(':', $serverAndPort);
        if (count($proxyPath) !== 2) {
            throw new SocksException(SocksException::PROXY_BAD_FORMAT);
        }

        $this->server = trim($proxyPath[0]);
        $this->port   = trim($proxyPath[1]);

        if ($type !== self::TYPE_HTTP && $type !== self::TYPE_SOCKS5) {
            throw new SocksException(SocksException::PROXY_BAD_FORMAT);
        }

        $this->type = $type;
    }

    public function setLoginPassword(?string $login, ?string $password): self
    {
        $this->login    = $login;
        $this->password = $password;

        return $this;
    }

    public function getServer(): string
    {
        return $this->server;
    }

    public function setServer(string $server): self
    {
        $this->server = $server;

        return $this;
    }

    public function getPort(): string
    {
        return $this->port;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function isNeedAuth(): bool
    {
        return $this->login && $this->password;
    }

    public function getLogin(): ?string
    {
        return $this->login;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function __toString(): string
    {
        return $this->server . ':' . $this->port;
    }
}
