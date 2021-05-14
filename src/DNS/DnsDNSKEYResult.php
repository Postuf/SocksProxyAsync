<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

use function base64_encode;
use function strlen;

final class DnsDNSKEYResult extends DnsResult
{
    private int $flags;
    private string $algorithm;
    private string $protocol;
    private bool $sep;
    private bool $zonekey;
    private int $keylength;
    private string $publickey;
    private string $publickeybase64;
    /** @psalm-suppress PropertyNotSetInConstructor */
    private int $keytag;

    public function __construct(int $flags, string $protocol, string $algorithm, string $pubkey)
    {
        parent::__construct();
        $this->setKeylength(strlen($pubkey));
        $this->setFlags($flags);
        $this->setProtocol($protocol);
        $this->setAlgorithm($algorithm);
        $this->setPublicKey($pubkey);
        $this->setPublicKeyBase64(base64_encode($pubkey));
        $this->sep     = false;
        $this->zonekey = false;
    }

    public function getFlags(): int
    {
        return $this->flags;
    }

    public function setFlags(int $flags): void
    {
        $this->flags = $flags;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function setProtocol(string $protocol): void
    {
        $this->protocol = $protocol;
    }

    public function isSep(): bool
    {
        return $this->sep;
    }

    public function setSep(bool $sep): void
    {
        $this->sep = $sep;
    }

    public function isZonekey(): bool
    {
        return $this->zonekey;
    }

    public function setZonekey(bool $zonekey): void
    {
        $this->zonekey = $zonekey;
    }

    public function getKeylength(): int
    {
        return $this->keylength;
    }

    public function setKeylength(int $keylength): void
    {
        $this->keylength = $keylength;
    }

    public function getPublickey(): string
    {
        return $this->publickey;
    }

    public function setPublickey(string $publickey): void
    {
        $this->publickey = $publickey;
    }

    public function getPublickeybase64(): string
    {
        return $this->publickeybase64;
    }

    public function setPublickeybase64(string $publickeybase64): void
    {
        $this->publickeybase64 = $publickeybase64;
    }

    public function getKeytag(): int
    {
        return $this->keytag;
    }

    public function setKeytag(int $keytag): void
    {
        $this->keytag = $keytag;
    }
}
