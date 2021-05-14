<?php

declare(strict_types=1);

namespace SocksProxyAsync;

final class SocketAsyncCallback extends SocketAsync
{
    public const STATE_READY = -1;

    /** @var callable */
    private $callback;

    private bool $called = false;

    /**
     * @param callable $cb function(SocketAsyncCallback $socket)
     */
    public function __construct(
        Proxy $proxy,
        string $host,
        int $port,
        callable $cb,
        int $timeOutSeconds = Constants::DEFAULT_TIMEOUT,
        ?string $dnsHostAndPort = null
    ) {
        parent::__construct($proxy, $host, $port, $timeOutSeconds, $dnsHostAndPort);
        $this->callback = $cb;
    }

    /**
     * @throws SocksException
     */
    protected function afterSteps(): bool
    {
        switch ($this->step->getStep()) {
            case self::STATE_READ_STATUS:
                if ($this->readSocksConnectStatus()) {
                    $this->step->finish();
                    $this->isReady = true;

                    $this->step->setStep(self::STATE_READY);
                }

                return true;

            case self::STATE_READY:
                if (! $this->called) {
                    $this->called = true;
                    $cb           = $this->callback;
                    $cb($this);
                }

                return true;
        }

        return false;
    }
}
