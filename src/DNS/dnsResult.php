<?php

/** @noinspection PhpUnused */

namespace SocksProxyAsync\DNS;

class dnsResult
{
    private $type;
    private $class;
    private $data;
    private $domain;
    private $record;
    /**
     * @var string|null
     */
    private $typeId;
    private $ttl;

    public function __construct()
    {
    }

    public function getType()
    {
        return $this->type;
    }

    public function setType($type): void
    {
        $this->type = $type;
    }

    public function setTypeId(string $typeId): void
    {
        $this->typeId = $typeId;
    }

    public function getTypeId(): ?string
    {
        return $this->typeId;
    }

    public function getClass()
    {
        return $this->class;
    }

    public function setClass($class): void
    {
        $this->class = $class;
    }

    public function setTtl($ttl): void
    {
        $this->ttl = $ttl;
    }

    public function getTtl()
    {
        return $this->ttl;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data): void
    {
        $this->data = $data;
    }

    public function getDomain()
    {
        return $this->domain;
    }

    public function setDomain($domain): void
    {
        $this->domain = $domain;
    }

    public function getRecord()
    {
        return $this->record;
    }

    public function setRecord($record): void
    {
        $this->record = $record;
    }
}
