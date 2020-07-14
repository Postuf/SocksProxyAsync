<?php

namespace SocksProxyAsync\DNS;

class dnsNSresult extends dnsResult
{
    /** @var string */
    private $nameserver;

    /**
     * dnsNSresult constructor.
     *
     * @param string $ns
     */
    public function __construct($ns)
    {
        parent::__construct();
        $this->setNameserver($ns);
    }

    /**
     * @param string $server
     */
    public function setNameserver($server): void
    {
        $this->nameserver = $server;
    }

    /**
     * @return string
     * @noinspection PhpUnused
     */
    public function getNameserver(): string
    {
        return $this->nameserver;
    }
}
