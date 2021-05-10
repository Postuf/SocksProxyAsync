<?php

declare(strict_types=1);

/** @noinspection PhpUnused */

namespace SocksProxyAsync\DNS;

/** @psalm-suppress PropertyNotSetInConstructor */
class DnsResult
{
    private int $type;
    private int $class;
    private string $data;
    private string $domain;
    private string $record;
    private ?string $typeId;
    private int $ttl;

    public function __construct()
    {
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function setType(int $type): void
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

    public function getClass(): int
    {
        return $this->class;
    }

    public function setClass(int $class): void
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

    public function getDomain(): string
    {
        return $this->domain;
    }

    public function setDomain(string $domain): void
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
