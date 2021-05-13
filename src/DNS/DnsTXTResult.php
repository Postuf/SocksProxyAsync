<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

final class DnsTXTResult extends DnsResult
{
    private string $record;

    public function __construct(string $record)
    {
        parent::__construct();
        $this->setRecord($record);
    }

    public function setRecord(string $record): void
    {
        $this->record = $record;
    }

    public function getRecord(): string
    {
        return $this->record;
    }
}
