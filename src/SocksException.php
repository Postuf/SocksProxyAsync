<?php

declare(strict_types=1);

namespace SocksProxyAsync;

use Exception;

class SocksException extends Exception
{
    public const UNREACHABLE_PROXY = 1000;
    public const UNEXPECTED_PROTOCOL_VERSION = 2000;
    public const UNSUPPORTED_AUTH_TYPE = 3000;
    public const CONNECTION_NOT_ESTABLISHED = 4000;
    public const AUTH_FAILED = 5000;
    public const RESPONSE_WAS_NOT_RECEIVED = 6000;
    public const PROXY_BAD_FORMAT = 7000;

    public function __construct(int $code, string $metaErrorInfo = '')
    {
        $message = $this->createMessageByCode($code);
        if ($metaErrorInfo) {
            $message .= "($metaErrorInfo)";
        }

        parent::__construct($message, $code, null);
    }

    private function createMessageByCode(int $code): string
    {
        switch ($code) {
            case self::UNREACHABLE_PROXY:
                return 'Proxy unreachable';
            case self::UNEXPECTED_PROTOCOL_VERSION:
                return 'Socks server has unexpected version';
            case self::UNSUPPORTED_AUTH_TYPE:
                return 'Server does not support basic auth type';
            case self::CONNECTION_NOT_ESTABLISHED:
                return 'Connection failed';
            case self::AUTH_FAILED:
                return 'Authorization via login/password failed';
            case self::RESPONSE_WAS_NOT_RECEIVED:
                return 'Proxy not responded on last message';
            default:
                return 'Code: '.$code;
        }
    }
}
