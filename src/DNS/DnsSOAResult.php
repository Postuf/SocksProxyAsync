<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

use function strpos;

/** @psalm-suppress PropertyNotSetInConstructor */
final class DnsSOAResult extends DnsResult
{
    private string $nameserver;
    private string $responsible;
    private int $serial;
    private int $refresh;
    private int $expiry;
    private int $retry;
    private int $minttl;

    public function setSerial(int $serial): void
    {
        $this->serial = $serial;
    }

    public function getSerial(): int
    {
        return $this->serial;
    }

    public function setExpiry(int $expiry): void
    {
        $this->expiry = $expiry;
    }

    public function getExpiry(): int
    {
        return $this->expiry;
    }

    public function setRetry(int $retry): void
    {
        $this->retry = $retry;
    }

    public function getRetry(): int
    {
        return $this->retry;
    }

    public function setMinttl(int $minttl): void
    {
        $this->minttl = $minttl;
    }

    public function getMinttl(): int
    {
        return $this->minttl;
    }

    public function setRefresh(int $refresh): void
    {
        $this->refresh = $refresh;
    }

    public function getRefresh(): int
    {
        return $this->refresh;
    }

    public function setResponsible(string $name): void
    {
        $dot               = strpos($name, '.');
        $name[(int) $dot]  = '@';
        $this->responsible = $name;
    }

    public function getResponsible(): string
    {
        return $this->responsible;
    }

    public function setNameserver(string $data): void
    {
        $this->nameserver = $data;
    }

    public function getNameserver(): string
    {
        return $this->nameserver;
    }
}
