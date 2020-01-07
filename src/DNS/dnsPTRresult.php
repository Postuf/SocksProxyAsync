<?php

namespace SocksProxyAsync\DNS;

class dnsPTRresult extends dnsResult
{
    private $data;

    public function __construct($data)
    {
        parent::__construct();
        $this->setData($data);
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}
