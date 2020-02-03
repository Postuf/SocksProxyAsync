<?php

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace SocksProxyAsync;

use Exception;
use SocksProxyAsync\DNS\dnsAresult;
use SocksProxyAsync\DNS\dnsException;
use SocksProxyAsync\DNS\dnsProtocol;
use SocksProxyAsync\DNS\dnsResponse;

/**
 * Class which manages native socket as socks5-connected socket
 * This class works only with SOCKS v5, supports only basic
 * authorization - without login:password.
 */
class SocketAsync extends Socks5Socket implements Async
{
    public const STATE_INITIAL = 0;
    public const STATE_RESOLVE = 5;
    public const STATE_CONNECT = 10;
    public const STATE_GREETING = 20;
    public const STATE_AUTH = 30;
    public const STATE_SOCKET_CONNECT = 40;
    public const STATE_READ_STATUS = 50;
    public const DEFAULT_DNS_SERVER = '8.8.8.8';
    private const ETC_RESOLV_CONF = '/etc/resolv.conf';
    private const ADDRESS_TYPE_A = 'A';
    private const DNS_TTL_SEC = 300;

    /**
     * @var AsyncStep
     */
    protected $step;

    /** @var bool */
    protected $isReady;

    /** @var dnsProtocol */
    protected $resolver;

    /** @var bool */
    protected $resolveCallbackSet = false;
    /** @var bool */
    protected $nameReady = false;
    /** @var string|null */
    private $dnsHostAndPort;
    /**
     * @var array[] name -> ['ipv4', time()]
     */
    protected static $dnsCache = [];

    /**
     * @param Proxy  $proxy
     * @param string $host
     * @param int    $port
     * @param int    $timeOutSeconds
     * @param string $dnsHostAndPort
     */
    public function __construct(
        Proxy $proxy,
        $host,
        $port,
        int $timeOutSeconds = Constants::SOCKET_CONNECT_TIMEOUT_SEC,
        ?string $dnsHostAndPort = null
    ) {
        parent::__construct($proxy, $timeOutSeconds);
        $this->host = $host;
        $this->port = $port;
        $this->step = new AsyncStep('Socks5SocketAsync_poll', $timeOutSeconds);
        $this->isReady = false;
        if (!$dnsHostAndPort) {
            $dnsHostAndPort = $this->getSystemDnsHost() ?: self::DEFAULT_DNS_SERVER;
        }

        $this->dnsHostAndPort = $dnsHostAndPort;
    }

    protected function clearResolver(): void
    {
        unset($this->resolver);
    }

    protected function getResolver(): dnsProtocol
    {
        if (!$this->resolver) {
            $dnsPort = dnsProtocol::DEFAULT_PORT;
            $dnsHost = $this->dnsHostAndPort;
            if (strpos($dnsHost, ':') !== false) {
                [$dnsHost, $dnsPort] = explode(':', $this->dnsHostAndPort);
            }

            $this->resolver = new dnsProtocol(false, (int) $dnsPort, true);
            $this->resolver->setServer($dnsHost);
        }

        return $this->resolver;
    }

    private function getSystemDnsHost(): ?string
    {
        if (!file_exists(self::ETC_RESOLV_CONF)) {
            return null;
        }

        $contents = file_get_contents(self::ETC_RESOLV_CONF);
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') !== false) {
                $line = substr($line, 0, strpos($line, '#'));
                $line = trim($line);
            }
            if (strpos($line, 'nameserver ') !== false) {
                $line = str_replace('nameserver ', '', $line);

                return trim($line);
            }
        }

        return null;
    }

    /**
     * Override this function to add more steps.
     *
     * @return bool true if step processed
     */
    protected function afterSteps(): bool
    {
        return false;
    }

    /**
     * @throws SocksException
     * @throws dnsException
     *
     * @return bool true if should not check step stuck
     */
    protected function baseSteps(): bool
    {
        switch ($this->step->getStep()) {
            case self::STATE_INITIAL:
                $this->createSocket();
                $this->step->setStep(self::STATE_RESOLVE);
                break;
            case self::STATE_RESOLVE:
                if (preg_match('/\d+\.\d+\.\d+\.\d+/', $this->proxy->getServer())) {
                    $this->step->setStep(self::STATE_CONNECT);
                } elseif ($this->proxy->getServer() === 'localhost') {
                    $this->proxy->setServer('127.0.0.1');
                } elseif ($this->hasDnsCache($this->proxy->getServer())) {
                    $this->proxy->setServer(self::$dnsCache[$this->proxy->getServer()][0]);
                    $this->nameReady = true;
                    $this->step->setStep(self::STATE_CONNECT);
                } else {
                    if (!$this->resolveCallbackSet) {
                        $this->getResolver()->QueryAsync(
                            $this->proxy->getServer(),
                            self::ADDRESS_TYPE_A,
                            function (?dnsResponse $result, ?string $error = null) {
                                if (!$error) {
                                    foreach ($result->getResourceResults() as $resource) {
                                        if ($resource instanceof dnsAresult) {
                                            self::$dnsCache[$this->proxy->getServer()] = [
                                                $resource->getIpv4(),
                                                time(),
                                            ];
                                            $this->proxy->setServer($resource->getIpv4());
                                        }
                                    }
                                } else {
                                    throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED, $error);
                                }
                                $this->nameReady = true;
                                $this->clearResolver();
                            }
                        );
                        $this->resolveCallbackSet = true;
                    }

                    if (!$this->nameReady) {
                        $this->getResolver()->poll();
                    }
                }
                break;
            case self::STATE_CONNECT:
                if ($this->connectSocket()) {
                    $this->writeSocksGreeting();
                    $this->step->setStep(self::STATE_GREETING);
                }
                break;
            case self::STATE_GREETING:
                $socksGreetingConfig = $this->readSocksGreeting();
                if ($socksGreetingConfig) {
                    $this->checkServerGreetedClient($socksGreetingConfig);
                    if ($this->checkGreetngWithAuth($socksGreetingConfig)) {
                        $this->writeSocksAuth();
                        $this->step->setStep(self::STATE_AUTH);
                    } else {
                        $this->step->setStep(self::STATE_SOCKET_CONNECT);
                    }
                }
                break;
            case self::STATE_AUTH:
                if ($this->readSocksAuthStatus()) {
                    $this->step->setStep(self::STATE_SOCKET_CONNECT);
                }
                break;
            case self::STATE_SOCKET_CONNECT:
                $this->connectSocksSocket();
                $this->step->setStep(self::STATE_READ_STATUS);
                break;
            case self::STATE_READ_STATUS:
                if ($this->readSocksConnectStatus()) {
                    $this->step->finish();
                    $this->isReady = true;

                    return true;
                }
                break;
        }

        return false;
    }

    /**
     * @throws SocksException
     */
    public function poll(): void
    {
        if (!$this->afterSteps()) {
            try {
                if ($this->baseSteps()) {
                    return;
                }
            } catch (dnsException $e) {
                throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED, $e->getMessage());
            }
        }

        try {
            $this->step->checkIfStepStuck();
        } catch (Exception $e) {
            $this->stop();

            throw new SocksException(SocksException::STEP_STUCK);
        }
    }

    public function ready(): bool
    {
        return $this->isReady;
    }

    private function hasDnsCache(string $server): bool
    {
        if (isset(self::$dnsCache[$server])) {
            if (self::$dnsCache[$server][1] < time() - self::DNS_TTL_SEC) {
                unset(self::$dnsCache[$server]);
            } else {
                return true;
            }
        }

        return false;
    }

    protected function createSocket(): void
    {
        $this->socksSocket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->socksSocket);
        $timeoutParams = [
            'sec'  => Constants::SOCKET_CONNECT_TIMEOUT_SEC,
            'usec' => 0,
        ];
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_RCVTIMEO, $timeoutParams);
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_SNDTIMEO, $timeoutParams);
        socket_clear_error($this->socksSocket);
    }

    /**
     * @throws SocksException
     *
     * @return bool
     */
    protected function connectSocket(): bool
    {
        if ($this->socksSocket != false) {
            @socket_connect($this->socksSocket, $this->proxy->getServer(), (int) $this->proxy->getPort());
            $lastError = socket_last_error($this->socksSocket);
            if ($lastError == SOCKET_EINPROGRESS || $lastError == SOCKET_EALREADY) {
                return false;
            } elseif ($lastError == SOCKET_EISCONN) {
                return true;
            } else {
                throw new SocksException(SocksException::UNREACHABLE_PROXY, 'on connect: '.$lastError);
            }
        }

        return false;
    }

    public function stop(): void
    {
        $this->disconnect();
    }
}
