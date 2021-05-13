<?php

declare(strict_types=1);

namespace SocksProxyAsync;

class SocketAsyncCallback extends SocketAsync
{
    public const STATE_READY = -1;

    /** @var callable */
    private $callback;

    /** @var bool */
    private bool $called = false;

    /**
     * SocketAsyncCallback constructor.
     *
     * @param Proxy       $proxy
     * @param string      $host
     * @param int         $port
     * @param callable    $cb             function(SocketAsyncCallback $socket)
     * @param int         $timeOutSeconds
     * @param string|null $dnsHostAndPort
     */
    public function __construct(
        Proxy $proxy,
        $host,
        $port,
        callable $cb,
        int $timeOutSeconds = Constants::DEFAULT_TIMEOUT,
        ?string $dnsHostAndPort = null
    ) {
        parent::__construct($proxy, $host, $port, $timeOutSeconds, $dnsHostAndPort);
        $this->callback = $cb;
    }

    /**
     * @throws SocksException
     *
     * @return bool
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
                if (!$this->called) {
                    $this->called = true;
                    $cb = $this->callback;
                    $cb($this);
                }

                return true;
        }

        return false;
    }
}
