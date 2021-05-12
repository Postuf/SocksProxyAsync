<?php

/** @noinspection SpellCheckingInspection */
declare(strict_types=1);

namespace SocksProxyAsync\DNS;

/**
 * @see https://tools.ietf.org/html/rfc1035
 */
class dnsAresult extends dnsResult
{
    /** @var string */
    private string $ipv4;

    public function __construct(string $ip)
    {
        parent::__construct();
        $this->setIpv4($ip);
    }

    public function setIpv4(string $ip): void
    {
        $this->ipv4 = $ip;
    }

    public function getIpv4(): string
    {
        return $this->ipv4;
    }
}
