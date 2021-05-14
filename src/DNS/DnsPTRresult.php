<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

final class DnsPTRresult extends DnsResult
{
    private string $data;

    public function __construct(string $data)
    {
        parent::__construct();
        $this->setData($data);
    }

    public function setData(string $data): void
    {
        $this->data = $data;
    }

    public function getData(): string
    {
        return $this->data;
    }
}
