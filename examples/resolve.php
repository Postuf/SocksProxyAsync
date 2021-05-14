<?php

declare(strict_types=1);

use SocksProxyAsync\DNS\DnsException;
use SocksProxyAsync\DNS\DnsProtocol;

require_once __DIR__.'/../vendor/autoload.php';

$proto = new DnsProtocol();
$proto->setServer('8.8.8.8');

try {
    $response = $proto->query('google.com');
    print_r($response);
} catch (DnsException $e) {
    print_r($e);
}
