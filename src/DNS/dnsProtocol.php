<?php

/** @noinspection PhpUnused */
/** @noinspection SpellCheckingInspection */

namespace SocksProxyAsync\DNS;

class dnsProtocol
{
    public const STATE_OPEN = 0;
    public const STATE_AWAITING = 1;
    public const STATE_AWAITING_TCP = 2;
    public const STATE_PRE_READY = 3;
    public const STATE_READY = -1;

    public const DEFAULT_PORT = 53;

    /** @var string */
    private $header;
    /** @var string */
    private $rawHeader;
    /** @var string */
    private $rawBuffer;
    /** @var string */
    private $rawResponse;

    /** @var string|null */
    private $returnSize;
    /**
     * @var bool
     */
    protected $logging;
    /**
     * @var array
     */
    protected $logEntries;
    /**
     * @var string
     */
    protected $server;
    /**
     * @var int default 53
     */
    protected $port;
    /**
     * @var float = 60
     */
    protected $timeout;
    /**
     * @var bool = false;
     */
    protected $udp;
    /**
     * @var array
     */
    protected $types;

    /** @var resource|null */
    private $socket = null;

    /** @var array|null */
    private $currentQuery = null;

    /** @var int */
    private $currentState = 0;
    /** @var float|null */
    private $awaitingStarted = null;

    /** @var callable|null */
    private $cb;

    /** @var string|null */
    private $requestHeader = null;
    /** @var int|null */
    private $requestHeaderSize = null;
    /** @var int|null */
    private $requestHeaderSizeBin = null;

    const DEFAULT_TIMEOUT = 60;

    /**
     * dnsProtocol constructor.
     *
     * @param bool $logging
     * @param int  $port
     * @param bool $udp
     */
    public function __construct($logging = false, int $port = self::DEFAULT_PORT, bool $udp = false)
    {
        if ($logging) {
            $this->enableLogging();
        }
        $this->port = $port;
        $this->timeout = self::DEFAULT_TIMEOUT;
        $this->udp = $udp;
        $this->types = new dnsTypes();
        set_error_handler([$this, 'error_handler']);
        $this->writelog('dnsProtocol Class Initialised');
    }

    public function __destruct()
    {
        if ($this->logging) {
            $this->showLog();
        }
    }

    /**
     * @param int         $errno
     * @param string|null $errStr
     * @param string|null $errFile
     * @param int|null    $errLine
     *
     * @throws dnsException
     *
     * @return bool
     */
    public function error_handler($errno = 0, $errStr = null, $errFile = null, $errLine = null)
    {
        // If error is suppressed with @, don't throw an exception
        if (error_reporting() === 0) {
            return true; // return true to continue through the others error handlers
        }

        throw new dnsException('Found '.$errStr.' in line '.$errLine.' of '.$errFile, $errno, null);
    }

    public function clear(): void
    {
        $this->socket = null;
        $this->currentQuery = null;
    }

    /**
     * @throws dnsException
     */
    public function poll(): void
    {
        switch ($this->currentState) {
            // we have a socket open
            case self::STATE_OPEN:
                if ($this->udp) {
                    if (!fwrite($this->socket, $this->requestHeader, $this->requestHeaderSize)) {
                        $this->closeWithError('Failed to write question to socket');

                        return;
                    }
                } else {
                    if (!fwrite($this->socket, $this->requestHeaderSizeBin)) { // write the socket
                        $this->closeWithError('Failed to write question length to TCP socket');

                        return;
                    }
                    if (!fwrite($this->socket, $this->requestHeader, $this->requestHeaderSize)) {
                        $this->closeWithError('Failed to write question to TCP socket');

                        return;
                    }
                }

                $this->setStateAwaiting();

                break;

            // waiting for response
            case self::STATE_AWAITING:
                if ($this->udp) {
                    $this->rawBuffer = fread($this->socket, 4096);
                    if (!$this->rawBuffer) { // read until the end with UDP
                        if (microtime(true) - $this->awaitingStarted > $this->timeout) {
                            $this->closeWithError('Failed to read (timeout)');
                        }

                        return;
                    }
                    $this->currentState = self::STATE_PRE_READY;
                } else {
                    $this->returnSize = fread($this->socket, 2);
                    if (!$this->returnSize) {
                        if (microtime(true) - $this->awaitingStarted > $this->timeout) {
                            $this->closeWithError('empty return');
                        }

                        return;
                    }
                    $this->currentState = self::STATE_AWAITING_TCP;
                }
                break;
            case self::STATE_AWAITING_TCP:
                $tmplen = unpack('nlength', $this->returnSize);
                $datasize = $tmplen['length'];
                $this->rawBuffer = fread($this->socket, $datasize);
                if (!$this->rawBuffer) {
                    if (microtime(true) - $this->awaitingStarted > $this->timeout) {
                        $this->closeWithError('Failed to read data buffer');
                    }

                    return;
                }
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($this->socket);
                $this->currentState = self::STATE_PRE_READY;

                break;
            case self::STATE_PRE_READY:
                $response = $this->decodeResponse();
                $cb = $this->cb;
                $this->currentState = self::STATE_READY;
                $cb($response);

                break;

            // all ready
            case self::STATE_READY:
                usleep(1000);
                break;
        }
    }

    /**
     * @param string   $question
     * @param string   $type
     * @param callable $cb       function(?dnsResponse $response)
     *
     * @throws dnsException
     */
    public function QueryAsync(string $question, string $type, callable $cb): void
    {
        $typeid = $this->types->getByName($type);
        if ($typeid === false) {
            throw new dnsException('Invalid Query Type '.$type);
        }

        if ($this->udp) {
            $host = 'udp://'.$this->server;
        } else {
            $host = $this->server;
        }
        if (!$this->socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout)) {
            throw new dnsException('Failed to open socket to '.$host);
        }

        stream_set_blocking($this->socket, false);
        list($header, $headersize, $headersizebin) = $this->prepareRequestHeaders($question, $type, $typeid);

        if (($this->udp) && ($headersize >= 512)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            fclose($this->socket);
            $this->socket = null;

            throw new dnsException('Question too big for UDP ('.$headersize.' bytes)');
        }

        $this->cb = $cb;
        $this->currentState = self::STATE_OPEN;
        $this->requestHeader = $header;
        $this->requestHeaderSize = $headersize;
        $this->requestHeaderSizeBin = $headersizebin;
    }

    /**
     * @param $question
     * @param string $type
     *
     * @throws dnsException
     *
     * @return dnsResponse
     */
    public function Query($question, $type = 'A')
    {
        $typeid = $this->types->getByName($type);
        if ($typeid === false) {
            throw new dnsException('Invalid Query Type '.$type);
        }

        if ($this->udp) {
            $host = 'udp://'.$this->server;
        } else {
            $host = $this->server;
        }
        if (!$socket = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout)) {
            throw new dnsException('Failed to open socket to '.$host);
        }

        list($header, $headerSize, $headerSizeBin) = $this->prepareRequestHeaders($question, $type, $typeid);

        if (($this->udp) && ($headerSize >= 512)) {
            stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            fclose($socket);

            throw new dnsException('Question too big for UDP ('.$headerSize.' bytes)');
        }

        if ($this->udp) { // UDP method
            if (!fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question to socket');
            }
            if (!$this->rawBuffer = fread($socket, 4096)) { // read until the end with UDP
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write read data buffer');
            }
        } else { // TCP method
            if (!fwrite($socket, $headerSizeBin)) { // write the socket
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question length to TCP socket');
            }
            if (!fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question to TCP socket');
            }
            if (!$returnsize = fread($socket, 2)) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }
            $tmplen = unpack('nlength', $returnsize);
            $datasize = $tmplen['length'];
            $this->writeLog('TCP Stream Length Limit '.$datasize);
            if (!$this->rawBuffer = fread($socket, $datasize)) {
                stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to read data buffer');
            }
        }
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        fclose($socket);

        $buffersize = strlen($this->rawBuffer);
        $this->writelog('Read Buffer Size '.$buffersize);
        if ($buffersize < 12) {
            throw new dnsException('DNS query return buffer too small');
        }

        return $this->prepareResponse();
    }

    public function setServer($server)
    {
        $this->server = $server;
    }

    public function getServer()
    {
        return $this->server;
    }

    /**
     * @param int $timeout
     *
     * @throws dnsException
     */
    public function setTimeout($timeout = self::DEFAULT_TIMEOUT)
    {
        if (!$new_timeout = floatval($timeout) || $timeout < 0) {
            throw new dnsException('Incorrect timeout value: <'.$timeout.'>. Timeout must be positive number.');
        }
        $this->timeout = $new_timeout;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setPort($port)
    {
        $this->port = $port;
    }

    public function getPort()
    {
        return $this->port;
    }

    private function enableLogging()
    {
        $this->logging = true;
    }

    private function showLog()
    {
        echo '==== LOG ====';
        foreach ($this->logEntries as $logentry) {
            echo $logentry."\n";
        }
    }

    private function writeLog($text)
    {
        if ($this->logging) {
            $this->logEntries[] = '-----'.date('Y-m-d H:i:s').'-----'.$text.'-----';
        }
    }

    /**
     * @see http://www.iana.org/protocols
     * @see http://www.iana.org/assignments/dns-sec-alg-numbers/dns-sec-alg-numbers.xml
     *
     * @param int $code
     *
     * @return string
     */
    public function algorithm(int $code)
    {
        switch ($code) {
            case 1:
                return 'md5';
            case 2:
                // Diffie-Helman
                return 'dh';
            case 3:
            case 5:
                return 'sha1';
            case 4:
            case 9:
            case 11:
                return 'reserved';
            case 6:
                return 'dsansec3sha1';
            case 7:
                return 'rsasha1nsec3';
            case 8:
                return 'sha256';
            case 10:
                return 'sha512';
            case 12:
                return 'gost';
            case 13:
                return 'ecdsa256';
            case 14:
                return 'ecdsa384';
            default:
                return 'unknown algorithm';
        }
    }

    public function registrynameservers($tld)
    {
        $ns = new dnsNameserver();

        return $ns->getNs($tld);
    }

    public function base32encode($input, $padding = true)
    {
        $map = [
            '0', '1', '2', '3', '4', '5', '6', '7', //  7
            '8', '9', 'a', 'b', 'c', 'd', 'e', 'f', // 15
            'g', 'h', 'i', 'j', 'k', 'l', 'm', 'n', // 23
            'o', 'p', 'q', 'r', 's', 't', 'u', 'v', // 31
            '=',  // padding char
        ];

        if (empty($input)) {
            return '';
        }
        $input = str_split($input);
        $binaryString = '';
        for ($i = 0; $i < count($input); $i++) {
            $binaryString .= str_pad(base_convert(ord($input[$i]), 10, 2), 8, '0', STR_PAD_LEFT);
        }
        $fiveBitBinaryArray = str_split($binaryString, 5);
        $base32 = '';
        $i = 0;
        while ($i < count($fiveBitBinaryArray)) {
            $base32 .= $map[base_convert(str_pad($fiveBitBinaryArray[$i], 5, '0'), 2, 10)];
            $i++;
        }
        if ($padding && ($x = strlen($binaryString) % 40) != 0) {
            if ($x == 8) {
                $base32 .= str_repeat($map[32], 6);
            } elseif ($x == 16) {
                $base32 .= str_repeat($map[32], 4);
            } elseif ($x == 24) {
                $base32 .= str_repeat($map[32], 3);
            } elseif ($x == 32) {
                $base32 .= $map[32];
            }
        }

        return $base32;
    }

    /** @noinspection PhpUnused */
    public function nsec3hash($qname, $salt = null, $iterations = '-')
    {
        $salt = pack('H*', $salt);
        $iterations = intval($iterations);
        $toHash = '';

        $qparts = explode('.', strtolower($qname).'.');
        foreach ($qparts as $part) {
            $toHash .= chr(strlen($part)).$part;
        }

        do {
            $toHash .= $salt;
            $toHash = sha1($toHash, true);
            $iterations--;
        } while ($iterations >= 0);

        return $this->base32encode($toHash);
    }

    /**
     * @throws dnsException
     *
     * @return dnsResponse
     */
    protected function decodeResponse(): ?dnsResponse
    {
        $buffersize = strlen($this->rawBuffer);
        $this->writelog('Read Buffer Size '.$buffersize);
        if ($buffersize < 12) {
            //throw new dnsException("DNS query return buffer too small");
            return null;
        }

        return $this->prepareResponse();
    }

    protected function closeWithError(string $error): void
    {
        stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
        fclose($this->socket);
        $this->currentState = self::STATE_READY;
        $cb = $this->cb;
        $cb(null, $error);
    }

    protected function setStateAwaiting(): void
    {
        $this->currentState = self::STATE_AWAITING;
        $this->awaitingStarted = microtime(true);
    }

    /**
     * @param string $question
     * @param string $type
     * @param int    $typeid
     *
     * @return array
     */
    protected function prepareRequestHeaders(string $question, string $type, int $typeid): array
    {
        $labels = [];
        // Split Into Labels
        if (preg_match('/[a-z|A-Z]/', $question) == 0) { // IP Address
            $labeltmp = explode('.', $question);    // reverse ARPA format
            for ($i = count($labeltmp) - 1; $i >= 0; $i--) {
                $labels[] = $labeltmp[$i];
            }
            $labels[] = 'IN-ADDR';
            $labels[] = 'ARPA';
        } else {
            $labels = explode('.', $question);
        }
        $question_binary = '';
        for ($a = 0; $a < count($labels); $a++) {
            $size = strlen($labels[$a]);
            $question_binary .= pack('C', $size); // size byte first
            $question_binary .= $labels[$a]; // then the label
        }
        $question_binary .= pack('C', 0); // end it off

        $this->writeLog('Question: '.$question.' (type='.$type.'/'.$typeid.')');

        $id = rand(1, 255) | (rand(0, 255) << 8);    // generate the ID

        // Set standard codes and flags
        $flags = (0x0100 & 0x0300) | 0x0020; // recursion & queryspecmask | authenticated data

        $opcode = 0x0000; // opcode

        // Build the header
        $header = '';
        $header .= pack('n', $id);
        $header .= pack('n', $opcode | $flags);
        $header .= pack('nnnn', 1, 0, 0, 0);
        $header .= $question_binary;
        $header .= pack('n', $typeid);
        $header .= pack('n', 0x0001); // internet class
        $headerSize = strlen($header);
        $headerSizeBin = pack('n', $headerSize);

        $this->writeLog('Header Length: '.$headerSize.' Bytes');

        return [$header, $headerSize, $headerSizeBin];
    }

    /**
     * @throws dnsException
     *
     * @return dnsResponse
     */
    protected function prepareResponse(): dnsResponse
    {
        $this->rawHeader = substr($this->rawBuffer, 0, 12); // first 12 bytes is the header
        $this->rawResponse = substr($this->rawBuffer, 12); // after that the response
        $this->header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', $this->rawHeader);
        $flags = sprintf("%016b\n", $this->header['flags']);
        $response = new dnsResponse();

        $response->setAuthorative($flags[5] == '1');
        $response->setTruncated($flags[6] == '1');
        $response->setRecursionRequested($flags[7] == '1');
        $response->setRecursionAvailable($flags[8] == '1');
        $response->setAuthenticated($flags[10] == '1');
        $response->setDnssecAware($flags[11] == '1');
        $response->setAnswerCount($this->header['ancount']);

        $this->writeLog('Query returned '.$this->header['ancount'].' Answers');

        // Deal with the header question data
        if ($this->header['qdcount'] > 0) {
            $response->setQueryCount($this->header['qdcount']);
            $this->writeLog('Found '.$this->header['qdcount'].' questions');
            $q = '';
            for ($a = 0; $a < $this->header['qdcount']; $a++) {
                $c = 1;
                while ($c != 0) {
                    $c = hexdec(bin2hex($response->ReadResponse($this->rawBuffer, 1)));
                    $q .= $c;
                }
                $response->addQuery($q);
                $response->ReadResponse($this->rawBuffer, 4);
            }
        }

        $this->writeLog('Found '.$this->header['ancount'].' answer records');
        $response->setResourceResultCount((int) $this->header['ancount']);
        for ($a = 0; $a < $this->header['ancount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_RESOURCE);
        }

        $this->writeLog('Found '.$this->header['nscount'].' authorative records');
        $response->setNameserverResultCount((int) $this->header['nscount']);
        for ($a = 0; $a < $this->header['nscount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_NAMESERVER);
        }
        $response->setAdditionalResultCount((int) $this->header['arcount']);
        $this->writeLog('Found '.$this->header['arcount'].' additional records');
        for ($a = 0; $a < $this->header['arcount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_ADDITIONAL);
        }

        return $response;
    }
}
