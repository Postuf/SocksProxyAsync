<?php

declare(strict_types=1);

namespace SocksProxyAsync;

final class Constants
{
    public const ASYNC_STEP_MAX_SEC             = 10;
    public const SOCKET_CONNECT_TIMEOUT_SEC     = 10;
    public const DEFAULT_TIMEOUT                = 30;
    public const ERR_SOCKET_ASYNC_STEP_FINISHED = 'err_socket_async_step_finished';
    public const ERR_SOCKET_ASYNC_STEP_TOO_LONG = 'err_socket_async_step_too_long';
}
