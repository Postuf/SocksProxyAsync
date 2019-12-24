<?php
namespace SocksProxyAsync;

/**
 * Class which manages native socket as socks5-connected socket
 * This class works only with SOCKS v5, supports only basic
 * authorization - without login:password
 * */
class SocketAsync extends Socks5Socket implements Async
{
    /**
     * @var AsyncStep
     */
    protected $step;

    /** @var bool */
    protected $isReady;

    /**
     * @param Proxy $proxy
     * @param string $host
     * @param int $port
     * @param int $timeOutSeconds
     */
    public function __construct(Proxy $proxy, $host, $port, int $timeOutSeconds = Constants::DEFAULT_TIMEOUT)
    {
        parent::__construct($proxy, $timeOutSeconds);
        $this->host = $host;
        $this->port = $port;
        $this->step = new AsyncStep('Socks5SocketAsync_poll', Constants::SOCKET_CONNECT_TIMEOUT_SEC);
        $this->isReady = false;
    }


    /**
     * @throws SocksException
     */
    public function poll(): void
    {
        switch ($this->step->getStep()) {
            case 0:
                $this->createSocket();
                $this->step->setStep(1);
                break;
            case 1:
                if($this->connectSocket()){
                    $this->writeSocksGreeting();
                    $this->step->setStep(2);
                }
                break;
            case 2:
                $socksGreetingConfig = $this->readSocksGreeting();
                if ($socksGreetingConfig){
                    $this->checkServerGreetedClient($socksGreetingConfig);
                    if($this->checkGreetngWithAuth($socksGreetingConfig)){
                        $this->writeSocksAuth();
                        $this->step->setStep(3);
                    } else {
                        $this->step->setStep(4);
                    }
                }
                break;
            case 3:
                if ($this->readSocksAuthStatus())
                    $this->step->setStep(4);
                break;
            case 4:
                $this->connectSocksSocket();
                $this->step->setStep(5);
                break;
            case 5:
                if($this->readSocksConnectStatus()) {
                    $this->step->finish();
                    $this->isReady = true;
                    return;
                }
                break;
        }

        try{
            $this->step->checkIfStepStuck();
        } catch (SocksException $e){
            $this->stop();
            throw $e;
        }
    }

    public function ready(): bool {
        return $this->isReady;
    }

    protected function createSocket()
    {
        $this->socksSocket = @socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_nonblock($this->socksSocket);
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_RCVTIMEO, [
            'sec' => Constants::SOCKET_CONNECT_TIMEOUT_SEC,
            'usec' => 0
        ]);
        socket_set_option($this->socksSocket, SOL_SOCKET, SO_SNDTIMEO, [
            'sec' => Constants::SOCKET_CONNECT_TIMEOUT_SEC,
            'usec' => 0
        ]);
        socket_clear_error($this->socksSocket);
    }

    /**
     * @return bool
     * @throws SocksException
     */
    protected function connectSocket()
    {
        if ($this->socksSocket !== false) {
            @socket_connect($this->socksSocket, $this->proxy->getServer(), $this->proxy->getPort());
            $lastError = socket_last_error($this->socksSocket);
            if ($lastError == SOCKET_EINPROGRESS || $lastError == SOCKET_EALREADY) {
                return false;
            } else if ($lastError == SOCKET_EISCONN) {
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