<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use SocksProxyAsync\DNS\DnsAResult;
use SocksProxyAsync\DNS\DnsMXResult;
use SocksProxyAsync\DNS\DnsProtocol;
use SocksProxyAsync\DNS\DnsResponse;

use function strtolower;

final class DnsTest extends TestCase
{
    public function testResolve(): void
    {
        $dns = new DnsProtocol();
        $dns->setServer('8.8.8.8');
        $question = 'google.com';
        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $dns->query($question, 'A');
        /** @var DnsResponse $result */
        $found = false;
        foreach ($result->getResourceResults() as $resource) {
            if (! ($resource instanceof DnsAResult)) {
                continue;
            }

            $found = true;
            self::assertEquals($question, strtolower($resource->getDomain()));
        }

        self::assertEquals(true, $found);

        /** @noinspection PhpUnhandledExceptionInspection */
        $result = $dns->query($question, 'MX');
        /** @var DnsResponse $result */
        $found = false;
        foreach ($result->getResourceResults() as $resource) {
            if (! ($resource instanceof DnsMXResult)) {
                continue;
            }

            $found = true;
            self::assertEquals($question, strtolower($resource->getDomain()));
        }

        self::assertEquals(true, $found);
    }
}
