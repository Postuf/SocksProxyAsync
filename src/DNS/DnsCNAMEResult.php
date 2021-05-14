<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

/**
 * @see https://tools.ietf.org/html/rfc1035
 */
final class DnsCNAMEResult extends DnsResult
{
    private string $redirect;

    public function __construct(string $redirect)
    {
        parent::__construct();
        $this->setRedirect($redirect);
    }

    public function setRedirect(string $redirect): void
    {
        $this->redirect = $redirect;
    }

    public function getRedirect(): string
    {
        return $this->redirect;
    }
}
