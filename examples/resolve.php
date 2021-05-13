<?php

declare(strict_types=1);

use SocksProxyAsync\DNS\dnsException;
use SocksProxyAsync\DNS\dnsProtocol;

require_once __DIR__.'/../vendor/autoload.php';

$proto = new dnsProtocol();
$proto->setServer('8.8.8.8');

try {
    $response = $proto->Query('google.com');
    print_r($response);
} catch (dnsException $e) {
    print_r($e);
}
