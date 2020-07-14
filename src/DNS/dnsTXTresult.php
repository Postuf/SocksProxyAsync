<?php

namespace SocksProxyAsync\DNS;

class dnsTXTresult extends dnsResult
{
    private $record;

    public function __construct($record)
    {
        parent::__construct();
        $this->setRecord($record);
    }

    public function setRecord($record): void
    {
        $this->record = $record;
    }

    public function getRecord()
    {
        return $this->record;
    }
}
