<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

final class DnsNSResult extends DnsResult
{
    private string $nameserver;

    public function __construct(string $ns)
    {
        parent::__construct();
        $this->setNameserver($ns);
    }

    public function setNameserver(string $server): void
    {
        $this->nameserver = $server;
    }

    public function getNameserver(): string
    {
        return $this->nameserver;
    }
}
