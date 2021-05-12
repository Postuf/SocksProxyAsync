<?php

namespace SocksProxyAsync\DNS;

class dnsMXresult extends dnsResult
{
    private $prio;
    private $server;

    public function setPrio($prio): void
    {
        $this->prio = $prio;
    }

    public function getPrio()
    {
        return $this->prio;
    }

    public function setServer($server): void
    {
        $this->server = $server;
    }

    public function getServer()
    {
        return $this->server;
    }
}
