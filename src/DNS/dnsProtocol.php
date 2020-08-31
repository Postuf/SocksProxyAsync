<?php

/** @noinspection PhpUnused */
/** @noinspection SpellCheckingInspection */

namespace SocksProxyAsync\DNS;

use Exception;

class dnsProtocol
{
    public const STATE_OPEN = 0;
    public const STATE_AWAITING = 1;
    public const STATE_AWAITING_TCP = 2;
    public const STATE_PRE_READY = 3;
    public const STATE_READY = -1;
    public const DEFAULT_PORT = 53;
    public const ERROR_CLOSING_ON_DESTRUCT = 'closing on destruct';
    private const DEFAULT_TIMEOUT = 60;

    /** @var string */
    private string $rawBuffer;

    /** @var string|null */
    private ?string $returnSize;
    /**
     * @var bool
     */
    private bool $logging = false;
    private array $logEntries;
    protected ?string $server = null;
    /**
     * @var int default 53
     */
    protected int $port;
    /**
     * @var float = 60
     */
    protected $timeout;
    /**
     * @var bool = false;
     */
    protected bool $udp;
    /**
     * @var array
     */
    protected $types;

    /** @var resource|null */
    private $socket;

    /** @var int */
    private int $currentState = 0;
    /** @var float|null */
    private ?float $awaitingStarted;

    /** @var callable|null */
    private $cb;

    /** @var string|null */
    private ?string $requestHeader;
    /** @var int|null */
    private ?int $requestHeaderSize;
    /** @var int|null */
    private ?int $requestHeaderSizeBin;

    /**
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
        $this->writelog('dnsProtocol Class Initialised');
    }

    public function __destruct()
    {
        if ($this->logging) {
            $this->showLog();
        }

        if ($this->cb && $this->currentState !== self::STATE_READY) {
            $this->closeWithError(self::ERROR_CLOSING_ON_DESTRUCT);
        }
    }

    public function clear(): void
    {
        $this->socket = null;
        $currentQuery = null;
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
                    @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                    @fclose($this->socket);
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
                @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                @fclose($this->socket);
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
        [$header, $headersize, $headersizebin] = $this->prepareRequestHeaders($question, $type, $typeid);

        if (($this->udp) && ($headersize >= 512)) {
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            @fclose($this->socket);
            $this->socket = null;

            throw new dnsException('Question too big for UDP ('.$headersize.' bytes)');
        }

        $this->cb = $cb;
        $this->currentState = self::STATE_OPEN;
        $this->requestHeader = $header;
        $this->requestHeaderSize = $headersize;
        $this->requestHeaderSizeBin = (int) $headersizebin;
    }

    /**
     * @param $question
     * @param string $type
     *
     * @throws dnsException
     *
     * @return dnsResponse
     */
    public function Query($question, $type = 'A'): dnsResponse
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

        [$header, $headerSize, $headerSizeBin] = $this->prepareRequestHeaders($question, $type, $typeid);

        if (($this->udp) && ($headerSize >= 512)) {
            stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
            fclose($socket);

            throw new dnsException('Question too big for UDP ('.$headerSize.' bytes)');
        }

        if ($this->udp) { // UDP method
            if (!fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question to socket');
            }
            if (!$this->rawBuffer = fread($socket, 4096)) { // read until the end with UDP
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write read data buffer');
            }
        } else { // TCP method
            if (!fwrite($socket, $headerSizeBin)) { // write the socket
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question length to TCP socket');
            }
            if (!fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to write question to TCP socket');
            }
            if (!$returnsize = fread($socket, 2)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }
            $tmplen = unpack('nlength', $returnsize);
            $datasize = $tmplen['length'];
            $this->writeLog('TCP Stream Length Limit '.$datasize);
            if (!$this->rawBuffer = fread($socket, $datasize)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new dnsException('Failed to read data buffer');
            }
        }
        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        fclose($socket);

        $buffersize = strlen($this->rawBuffer);
        $this->writelog('Read Buffer Size '.$buffersize);
        if ($buffersize < 12) {
            throw new dnsException('DNS query return buffer too small');
        }

        return $this->prepareResponse();
    }

    public function setServer($server): void
    {
        $this->server = $server;
    }

    public function getServer(): string
    {
        return $this->server;
    }

    /**
     * @param int $timeout
     *
     * @throws dnsException
     */
    public function setTimeout(int $timeout = self::DEFAULT_TIMEOUT): void
    {
        if ($timeout < 0) {
            throw new dnsException('Incorrect timeout value: <'.$timeout.'>. Timeout must be positive number.');
        }
        $this->timeout = $timeout;
    }

    public function getTimeout()
    {
        return $this->timeout;
    }

    public function setPort($port): void
    {
        $this->port = $port;
    }

    public function getPort(): int
    {
        return $this->port;
    }

    private function enableLogging(): void
    {
        $this->logging = true;
    }

    private function showLog(): void
    {
        echo '==== LOG ====';
        foreach ($this->logEntries as $logentry) {
            echo $logentry."\n";
        }
    }

    private function writeLog($text): void
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
    public function algorithm(int $code): ?string
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

    public function registrynameservers($tld): array
    {
        return (new dnsNameserver())->getNs($tld);
    }

    /** @noinspection TypeUnsafeComparisonInspection */
    public function base32encode($input, $padding = true): string
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
        foreach ($input as $iValue) {
            $binaryString .= str_pad(base_convert(ord($iValue), 10, 2), 8, '0', STR_PAD_LEFT);
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
    public function nsec3hash($qname, $salt = null, $iterations = '-'): string
    {
        $salt = pack('H*', $salt);
        $iterationsCount = (int) $iterations;
        $toHash = '';

        foreach (explode('.', strtolower($qname).'.') as $part) {
            $toHash .= chr(strlen($part)).$part;
        }

        do {
            $toHash .= $salt;
            $toHash = sha1($toHash, true);
            $iterationsCount--;
        } while ($iterationsCount >= 0);

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
        if ($this->socket) {
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            @fclose($this->socket);
        }
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
     * @noinspection TypeUnsafeComparisonInspection
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
        foreach ($labels as $aValue) {
            $size = strlen($aValue);
            $question_binary .= pack('C', $size); // size byte first
            $question_binary .= $aValue; // then the label
        }
        $question_binary .= pack('C', 0); // end it off

        $this->writeLog('Question: '.$question.' (type='.$type.'/'.$typeid.')');

        try {
            $id = random_int(1, 255) | (random_int(0, 255) << 8);
        } catch (Exception $e) {
            $id = 0;
        } // generate the ID

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
     * @noinspection TypeUnsafeComparisonInspection
     */
    protected function prepareResponse(): dnsResponse
    {
        $rawHeader = substr($this->rawBuffer, 0, 12); // first 12 bytes is the header
        $header = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', $rawHeader);
        $flags = sprintf("%016b\n", $header['flags']);
        $response = new dnsResponse();

        $response->setAuthorative($flags[5] == '1');
        $response->setTruncated($flags[6] == '1');
        $response->setRecursionRequested($flags[7] == '1');
        $response->setRecursionAvailable($flags[8] == '1');
        $response->setAuthenticated($flags[10] == '1');
        $response->setDnssecAware($flags[11] == '1');
        $response->setAnswerCount((int) $header['ancount']);

        $this->writeLog('Query returned '.$header['ancount'].' Answers');

        // Deal with the header question data
        if ($header['qdcount'] > 0) {
            $response->setQueryCount((int) $header['qdcount']);
            $this->writeLog('Found '.$header['qdcount'].' questions');
            $q = '';
            for ($a = 0; $a < $header['qdcount']; $a++) {
                $c = 1;
                while ($c !== 0) {
                    $c = hexdec(bin2hex($response->ReadResponse($this->rawBuffer, 1)));
                    $q .= $c;
                }
                $response->addQuery($q);
                /** @noinspection UnusedFunctionResultInspection */
                $response->ReadResponse($this->rawBuffer, 4);
            }
        }

        $this->writeLog('Found '.$header['ancount'].' answer records');
        $response->setResourceResultCount((int) $header['ancount']);
        for ($a = 0; $a < $header['ancount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_RESOURCE);
        }

        $this->writeLog('Found '.$header['nscount'].' authorative records');
        $response->setNameserverResultCount((int) $header['nscount']);
        for ($a = 0; $a < $header['nscount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_NAMESERVER);
        }
        $response->setAdditionalResultCount((int) $header['arcount']);
        $this->writeLog('Found '.$header['arcount'].' additional records');
        for ($a = 0; $a < $header['arcount']; $a++) {
            $response->ReadRecord($this->rawBuffer, dnsResponse::RESULTTYPE_ADDITIONAL);
        }

        return $response;
    }
}
