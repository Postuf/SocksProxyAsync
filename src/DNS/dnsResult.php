<?php

/** @noinspection PhpUnused */

namespace SocksProxyAsync\DNS;

class dnsResult
{
    private $type;
    private $class;
    private $data;
    private $domain;
    private string $record;
    /**
     * @var string|null
     */
    private ?string $typeId;
    private int $ttl;

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

    public function setTtl(int $ttl): void
    {
        $this->ttl = $ttl;
    }

    public function getTtl(): int
    {
        return $this->ttl;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function setData(string $data): void
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

    public function getRecord(): string
    {
        return $this->record;
    }

    public function setRecord(string $record): void
    {
        $this->record = $record;
    }
}
