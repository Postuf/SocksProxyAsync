<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

final class DnsDSResult extends DnsResult
{
    private int $keytag;
    private string $algorithm;
    private string $digest;
    private string $key;
    private string $rest;

    public function __construct(int $keytag, string $algorithm, string $digest, string $key, string $rest)
    {
        parent::__construct();
        $this->setKeytag($keytag);
        $this->setAlgorithm($algorithm);
        $this->setDigest($digest);
        $this->setKey($key);
        $this->setRest($rest);
    }

    public function setKeytag(int $keytag): void
    {
        $this->keytag = $keytag;
    }

    public function getKeytag(): int
    {
        return $this->keytag;
    }

    public function setAlgorithm(string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setDigest(string $digest): void
    {
        $this->digest = $digest;
    }

    public function getDigest(): string
    {
        return $this->digest;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function setRest(string $rest): void
    {
        $this->rest = $rest;
    }

    public function getRest(): string
    {
        return $this->rest;
    }
}
