<?php

declare(strict_types=1);

namespace Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\DNS\dnsAresult;
use SocksProxyAsync\DNS\dnsMXresult;
use SocksProxyAsync\DNS\dnsProtocol;
use SocksProxyAsync\DNS\dnsResponse;

class DnsTest extends TestCase
{
    public function test_resolve(): void
    {
        $dns = new dnsProtocol();
        $dns->setServer('8.8.8.8');
        $question = 'google.com';
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $dns->Query($question, 'A');
        /* @var $result dnsResponse */
        $found = false;
        foreach ($result->getResourceResults() as $resource) {
            if ($resource instanceof dnsAresult) {
                $found = true;
                self::assertEquals($question, strtolower($resource->getDomain()));
            }
        }
        self::assertEquals(true, $found);

        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $dns->Query($question, 'MX');
        /* @var $result dnsResponse */
        $found = false;
        foreach ($result->getResourceResults() as $resource) {
            if ($resource instanceof dnsMXresult) {
                $found = true;
                self::assertEquals($question, strtolower($resource->getDomain()));
            }
        }

        self::assertEquals(true, $found);
    }
}
