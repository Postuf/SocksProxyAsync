<?php

/** @noinspection SpellCheckingInspection */

declare(strict_types=1);

namespace SocksProxyAsync;

use SocksProxyAsync\DNS\DnsAResult;
use SocksProxyAsync\DNS\DnsException;
use SocksProxyAsync\DNS\DnsProtocol;
use SocksProxyAsync\DNS\DnsResponse;
use Throwable;

use function explode;
use function is_file;
use function file_get_contents;
use function preg_match;
use function socket_connect;
use function socket_create;
use function socket_set_nonblock;
use function socket_set_option;
use function substr;
use function socket_clear_error;
use function socket_last_error;
use function str_replace;
use function strpos;
use function time;
use function trim;

use const AF_INET;
use const SO_RCVTIMEO;
use const SO_SNDTIMEO;
use const SOCK_STREAM;
use const SOCKET_EALREADY;
use const SOCKET_EINPROGRESS;
use const SOCKET_EISCONN;
use const SOL_SOCKET;
use const SOL_TCP;

/**
 * Class which manages native socket as socks5-connected socket
 * This class works only with SOCKS v5, supports only basic
 * authorization - without login:password.
 */
class SocketAsync extends Socks5Socket implements Async
{
    public const STATE_INITIAL        = 0;
    public const STATE_RESOLVE        = 5;
    public const STATE_CONNECT        = 10;
    public const STATE_GREETING       = 20;
    public const STATE_AUTH           = 30;
    public const STATE_SOCKET_CONNECT = 40;
    public const STATE_READ_STATUS    = 50;
    public const DEFAULT_DNS_SERVER   = '8.8.8.8';
    private const ETC_RESOLV_CONF     = '/etc/resolv.conf';
    private const ADDRESS_TYPE_A      = 'A';
    private const DNS_TTL_SEC         = 300;

    protected AsyncStep $step;

    protected bool $isReady;

    protected ?DnsProtocol $resolver = null;

    protected bool $resolveCallbackSet = false;
    protected bool $nameReady          = false;
    private ?string $dnsHostAndPort;
    /**
     * @var array<array-key,list<string,int>>
     * name -> ['ipv4', time()]
     */
    protected static array $dnsCache = [];

    public function __construct(
        Proxy $proxy,
        string $host,
        int $port,
        int $timeOutSeconds = Constants::SOCKET_CONNECT_TIMEOUT_SEC,
        ?string $dnsHostAndPort = null
    ) {
        parent::__construct($proxy, $timeOutSeconds);
        $this->host    = $host;
        $this->port    = $port;
        $this->step    = new AsyncStep('Socks5SocketAsync_poll', $timeOutSeconds);
        $this->isReady = false;
        if (! $dnsHostAndPort) {
            $dnsHostAndPort = $this->getSystemDnsHost() ?: self::DEFAULT_DNS_SERVER;
        }

        $this->dnsHostAndPort = $dnsHostAndPort;
    }

    protected function clearResolver(): void
    {
        unset($this->resolver);
    }

    protected function getResolver(): DnsProtocol
    {
        if (! $this->resolver) {
            $dnsPort = DnsProtocol::DEFAULT_PORT;
            $dnsHost = $this->dnsHostAndPort;
            if (strpos((string) $dnsHost, ':') !== false) {
                /** @psalm-suppress PossiblyNullArgument */
                [$dnsHost, $dnsPort] = explode(':', $this->dnsHostAndPort);
            }

            $this->resolver = new DnsProtocol(false, (int) $dnsPort, true);
            /** @psalm-suppress PossiblyNullArgument */
            $this->resolver->setServer($dnsHost);
        }

        return $this->resolver;
    }

    private function getSystemDnsHost(): ?string
    {
        if (! is_file(self::ETC_RESOLV_CONF)) {
            return null;
        }

        $contents = file_get_contents(self::ETC_RESOLV_CONF);
        foreach (explode("\n", $contents) as $line) {
            $line = trim($line);
            if (strpos($line, '#') !== false) {
                /** @psalm-suppress PossiblyFalseArgument */
                $line = trim(substr($line, 0, strpos($line, '#')));
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
     * @return bool true if should not check step stuck
     *
     * @throws DnsException
     * @throws SocksException
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
                    $this->proxy->setServer((string)self::$dnsCache[$this->proxy->getServer()][0]);
                    $this->nameReady = true;
                    $this->step->setStep(self::STATE_CONNECT);
                } else {
                    if (! $this->resolveCallbackSet) {
                        $this->getResolver()->queryAsync(
                            $this->proxy->getServer(),
                            self::ADDRESS_TYPE_A,
                            $this->getDnsResolveHandler()
                        );
                        $this->resolveCallbackSet = true;
                    }

                    if (! $this->nameReady) {
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
                    if ($this->checkGreetingWithAuth($socksGreetingConfig)) {
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

    private function getDnsResolveHandler(): callable
    {
        return function (?DnsResponse $result, ?string $error = null): void {
            if ($error !== null || $result === null) {
                if ($error !== DnsProtocol::ERROR_CLOSING_ON_DESTRUCT) {
                    throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED, (string) $error);
                }

                return;
            }

            foreach ($result->getResourceResults() as $resourceResult) {
                if (! ($resourceResult instanceof DnsAResult)) {
                    continue;
                }

                /** @psalm-suppress InvalidPropertyAssignmentValue */
                self::$dnsCache[$this->proxy->getServer()] = [
                    $resourceResult->getIpv4(),
                    time(),
                ];
                $this->proxy->setServer($resourceResult->getIpv4());
            }

            $this->nameReady = true;
            $this->clearResolver();
        };
    }

    /**
     * @throws SocksException
     */
    public function poll(): void
    {
        if (! $this->afterSteps()) {
            try {
                if ($this->baseSteps()) {
                    return;
                }
                /** @phpstan-ignore-next-line */
            } catch (DnsException $e) {
                throw new SocksException(SocksException::CONNECTION_NOT_ESTABLISHED, $e->getMessage());
            }
        }

        try {
            $this->step->checkIfStepStuck();
            /** @phpstan-ignore-next-line */
        } catch (Throwable $e) {
            $this->stop();

            throw new SocksException(SocksException::STEP_STUCK, $e->getMessage());
        }
    }

    public function ready(): bool
    {
        return $this->isReady;
    }

    private function hasDnsCache(string $server): bool
    {
        if (isset(self::$dnsCache[$server])) {
            if (self::$dnsCache[$server][1] >= time() - self::DNS_TTL_SEC) {
                return true;
            }

            unset(self::$dnsCache[$server]);
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
     */
    protected function connectSocket(): bool
    {
        if ($this->socksSocket !== false) {
            @socket_connect($this->socksSocket, $this->proxy->getServer(), (int) $this->proxy->getPort());
            $lastError = socket_last_error($this->socksSocket);
            if ($lastError === SOCKET_EINPROGRESS || $lastError === SOCKET_EALREADY) {
                return false;
            }

            if ($lastError === SOCKET_EISCONN) {
                return true;
            }

            throw new SocksException(SocksException::UNREACHABLE_PROXY, 'on connect: '.$lastError);
        }

        return false;
    }

    public function stop(): void
    {
        $this->disconnect();
    }
}
