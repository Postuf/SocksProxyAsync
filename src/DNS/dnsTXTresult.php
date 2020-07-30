<?php

namespace SocksProxyAsync\DNS;

class dnsTXTresult extends dnsResult
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
