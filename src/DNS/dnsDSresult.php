<?php

namespace SocksProxyAsync\DNS;

class dnsDSresult extends dnsResult
{
    private $keytag;
    private $algorithm;
    private $digest;
    private $key;
    private $rest;

    public function __construct($keytag, $algorithm, $digest, $key, $rest)
    {
        parent::__construct();
        $this->setKeytag($keytag);
        $this->setAlgorithm($algorithm);
        $this->setDigest($digest);
        $this->setKey($key);
        $this->setRest($rest);
    }

    public function setKeytag($keytag): void
    {
        $this->keytag = $keytag;
    }

    public function getKeytag()
    {
        return $this->keytag;
    }

    public function setAlgorithm($algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function getAlgorithm()
    {
        return $this->algorithm;
    }

    public function setDigest($digest): void
    {
        $this->digest = $digest;
    }

    public function getDigest()
    {
        return $this->digest;
    }

    public function setKey($key): void
    {
        $this->key = $key;
    }

    public function getKey()
    {
        return $this->key;
    }

    public function setRest($rest): void
    {
        $this->rest = $rest;
    }

    public function getRest()
    {
        return $this->rest;
    }
}
