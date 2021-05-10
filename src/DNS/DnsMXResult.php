<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

/** @psalm-suppress PropertyNotSetInConstructor */
class DnsMXResult extends DnsResult
{
    private int $prio;
    private string $server;

    public function setPrio(int $prio): void
    {
        $this->prio = $prio;
    }

    public function getPrio(): int
    {
        return $this->prio;
    }

    public function setServer(string $server): void
    {
        $this->server = $server;
    }

    public function getServer(): string
    {
        return $this->server;
    }
}
