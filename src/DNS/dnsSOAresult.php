<?php

namespace SocksProxyAsync\DNS;

class dnsSOAresult extends dnsResult
{
    private $nameserver;
    private $responsible;
    private $serial;
    private $refresh;
    private $expiry;
    private $retry;
    private $minttl;

    public function setSerial($serial): void
    {
        $this->serial = $serial;
    }

    public function getSerial()
    {
        return $this->serial;
    }

    public function setExpiry($expiry): void
    {
        $this->expiry = $expiry;
    }

    public function getExpiry()
    {
        return $this->expiry;
    }

    public function setRetry($retry): void
    {
        $this->retry = $retry;
    }

    public function getRetry()
    {
        return $this->retry;
    }

    public function setMinttl($minttl): void
    {
        $this->minttl = $minttl;
    }

    public function getMinttl()
    {
        return $this->minttl;
    }

    public function setRefresh($refresh): void
    {
        $this->refresh = $refresh;
    }

    public function getRefresh()
    {
        return $this->refresh;
    }

    public function setResponsible($name): void
    {
        $dot = strpos($name, '.');
        $name[$dot] = '@';
        $this->responsible = $name;
    }

    public function getResponsible()
    {
        return $this->responsible;
    }

    public function setNameserver($data): void
    {
        $this->nameserver = $data;
    }

    public function getNameserver()
    {
        return $this->nameserver;
    }
}
