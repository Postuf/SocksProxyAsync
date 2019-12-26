<?php

declare(strict_types=1);

namespace SocksProxyAsync;

interface Async
{
    public function poll(): void;

    public function stop(): void;
}
