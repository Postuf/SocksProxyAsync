<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

use Safe\Exceptions\FilesystemException;
use Safe\Exceptions\NetworkException;
use Throwable;

use function base_convert;
use function bin2hex;
use function chr;
use function count;
use function explode;
use function hexdec;
use function microtime;
use function ord;
use function random_int;
use function Safe\date;
use function Safe\fclose;
use function Safe\fread;
use function Safe\fsockopen;
use function Safe\fwrite;
use function Safe\pack;
use function Safe\preg_match;
use function Safe\sprintf;
use function Safe\stream_set_blocking;
use function Safe\stream_socket_shutdown;
use function Safe\substr;
use function Safe\unpack;
use function sha1;
use function str_pad;
use function str_repeat;
use function str_split;
use function strlen;
use function strtolower;
use function usleep;

use const STR_PAD_LEFT;
use const STREAM_SHUT_RDWR;

/** @psalm-suppress PropertyNotSetInConstructor */
final class DnsProtocol
{
    public const STATE_OPEN                = 0;
    public const STATE_AWAITING            = 1;
    public const STATE_AWAITING_TCP        = 2;
    public const STATE_PRE_READY           = 3;
    public const STATE_READY               = -1;
    public const DEFAULT_PORT              = 53;
    public const ERROR_CLOSING_ON_DESTRUCT = 'closing on destruct';
    private const DEFAULT_TIMEOUT          = 60;

    private string $rawBuffer;

    private ?string $returnSize;
    private bool $logging = false;
    /** @var string[] */
    private array $logEntries;
    protected ?string $server = null;
    /** @var int default 53 */
    protected int $port;
    /** @var float = 60 */
    protected float $timeout;
    /** @var bool = false; */
    protected bool $udp;
    protected DnsTypes $types;

    /** @var resource|null */
    private $socket;

    private int $currentState = 0;
    private ?float $awaitingStarted;

    /** @var callable|null */
    private $cb;

    private ?string $requestHeader;
    private ?int $requestHeaderSize;
    private ?string $requestHeaderSizeBin;

    public function __construct(bool $logging = false, int $port = self::DEFAULT_PORT, bool $udp = false)
    {
        if ($logging) {
            $this->enableLogging();
        }

        $this->port    = $port;
        $this->timeout = self::DEFAULT_TIMEOUT;
        $this->udp     = $udp;
        $this->types   = new DnsTypes();
        $this->writelog('dnsProtocol Class Initialised');
    }

    public function __destruct()
    {
        if ($this->logging) {
            $this->showLog();
        }

        if (! $this->cb || $this->currentState === self::STATE_READY) {
            return;
        }

        $this->closeWithError(self::ERROR_CLOSING_ON_DESTRUCT);
    }

    public function clear(): void
    {
        $this->socket = null;
    }

    /**
     * @throws DnsException
     */
    public function poll(): void
    {
        switch ($this->currentState) {
            // we have a socket open
            case self::STATE_OPEN:
                if ($this->udp === true) {
                    try {
                        /** @psalm-suppress PossiblyNullArgument */
                        fwrite($this->socket, $this->requestHeader, $this->requestHeaderSize);
                    } catch (FilesystemException $e) {
                        $this->closeWithError('Failed to write question to socket');

                        return;
                    }
                } else {
                    try {
                        /** @psalm-suppress PossiblyNullArgument */
                        fwrite($this->socket, $this->requestHeaderSizeBin);
                    } catch (FilesystemException $e) {
                        $this->closeWithError('Failed to write question length to TCP socket');

                        return;
                    }

                    try {
                        /** @psalm-suppress PossiblyNullArgument */
                        fwrite($this->socket, $this->requestHeader, $this->requestHeaderSize);
                    } catch (FilesystemException $e) {
                        $this->closeWithError('Failed to write question to TCP socket');

                        return;
                    }
                }

                $this->setStateAwaiting();

                break;

            // waiting for response
            case self::STATE_AWAITING:
                if ($this->udp) {
                    /** @psalm-suppress PossiblyNullArgument */
                    $this->rawBuffer = fread($this->socket, 4096);
                    if (! $this->rawBuffer) { // read until the end with UDP
                        if (microtime(true) - (float) $this->awaitingStarted > $this->timeout) {
                            $this->closeWithError('Failed to read (timeout)');
                        }

                        return;
                    }

                    /** @psalm-suppress PossiblyNullArgument */
                    @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                    /** @psalm-suppress PossiblyNullArgument */
                    @fclose($this->socket);
                    $this->currentState = self::STATE_PRE_READY;
                } else {
                    /** @psalm-suppress PossiblyNullArgument */
                    $this->returnSize = fread($this->socket, 2);
                    if (! $this->returnSize) {
                        if (microtime(true) - (float) $this->awaitingStarted > $this->timeout) {
                            $this->closeWithError('empty return');
                        }

                        return;
                    }

                    $this->currentState = self::STATE_AWAITING_TCP;
                }

                break;
            case self::STATE_AWAITING_TCP:
                $tmplen   = unpack('nlength', (string) $this->returnSize);
                $datasize = (int) $tmplen['length'];
                /** @psalm-suppress PossiblyNullArgument */
                $this->rawBuffer = fread($this->socket, $datasize);
                if (! $this->rawBuffer) {
                    if (microtime(true) - (float) $this->awaitingStarted > $this->timeout) {
                        $this->closeWithError('Failed to read data buffer');
                    }

                    return;
                }

                /** @psalm-suppress PossiblyNullArgument */
                    @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                /** @psalm-suppress PossiblyNullArgument */
                    @fclose($this->socket);

                $this->currentState = self::STATE_PRE_READY;

                break;
            case self::STATE_PRE_READY:
                $response           = $this->decodeResponse();
                $cb                 = $this->cb;
                $this->currentState = self::STATE_READY;

                if ($cb !== null) {
                    $cb($response);
                }

                break;

            // all ready
            case self::STATE_READY:
                usleep(1000);
                break;
        }
    }

    /**
     * @param callable $cb function(?dnsResponse $response)
     *
     * @throws DnsException
     */
    public function queryAsync(string $question, string $type, callable $cb): void
    {
        $typeid = $this->types->getByName($type);

        $host = $this->udp === true ? 'udp://' . (string) $this->server : (string) $this->server;

        try {
            fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            /** @phpstan-ignore-next-line */
        } catch (NetworkException $e) {
            throw new DnsException('Failed to open socket to ' . $host);
        }

        /** @psalm-suppress PossiblyNullArgument */
        stream_set_blocking($this->socket, false);

        /**
         * @var string $header
         * @var int $headerSize
         * @var string $headerSizeBin
         */
        [$header, $headerSize, $headerSizeBin] = $this->prepareRequestHeaders($question, $type, $typeid);

        if ($this->udp === true && ($headerSize >= 512)) {
            if ($this->socket !== null) {
                @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
                @fclose($this->socket);
                $this->socket = null;
            }

            throw new DnsException('Question too big for UDP (' . $headerSize . ' bytes)');
        }

        $this->cb                   = $cb;
        $this->currentState         = self::STATE_OPEN;
        $this->requestHeader        = $header;
        $this->requestHeaderSize    = $headerSize;
        $this->requestHeaderSizeBin = $headerSizeBin;
    }

    /**
     * @throws DnsException
     */
    public function query(string $question, string $type = 'A'): DnsResponse
    {
        $typeid = $this->types->getByName($type);

        $host = $this->udp === true ? 'udp://' . (string) $this->server : (string) $this->server;

        try {
            $socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            /** @phpstan-ignore-next-line */
        } catch (NetworkException $e) {
            throw new DnsException('Failed to open socket to ' . $host);
        }

        /**
         * @var string $header
         * @var int $headerSize
         * @var string $headerSizeBin
         */
        [$header, $headerSize, $headerSizeBin] = $this->prepareRequestHeaders($question, $type, $typeid);

        if ($this->udp && ($headerSize >= 512)) {
            stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
            fclose($socket);

            throw new DnsException('Question too big for UDP (' . $headerSize . ' bytes)');
        }

        if ($this->udp) { // UDP method
            if (! fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new DnsException('Failed to write question to socket');
            }

            try {
                $this->rawBuffer = fread($socket, 4096);
                /** @phpstan-ignore-next-line */
            } catch (FilesystemException $e) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new DnsException('Failed to write read data buffer');
            }
        } else { // TCP method
            if (! fwrite($socket, $headerSizeBin)) { // write the socket
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new DnsException('Failed to write question length to TCP socket');
            }

            if (! fwrite($socket, $header, $headerSize)) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new DnsException('Failed to write question to TCP socket');
            }

            $returnSize = fread($socket, 2);
            if ($returnSize === '') {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);
            }

            $tmplen   = unpack('nlength', $returnSize);
            $datasize = (string) $tmplen['length'];
            $this->writeLog('TCP Stream Length Limit ' . $datasize);

            try {
                $this->rawBuffer = fread($socket, (int) $datasize);
                /** @phpstan-ignore-next-line */
            } catch (FilesystemException $e) {
                stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
                fclose($socket);

                throw new DnsException('Failed to read data buffer');
            }
        }

        stream_socket_shutdown($socket, STREAM_SHUT_RDWR);
        fclose($socket);

        $buffersize = strlen($this->rawBuffer);
        $this->writelog('Read Buffer Size ' . $buffersize);
        if ($buffersize < 12) {
            throw new DnsException('DNS query return buffer too small');
        }

        return $this->prepareResponse();
    }

    public function setServer(string $server): void
    {
        $this->server = $server;
    }

    public function getServer(): string
    {
        return (string) $this->server;
    }

    public function setTimeout(float $timeout = self::DEFAULT_TIMEOUT): void
    {
        if ($timeout < 0) {
            throw new DnsException('Incorrect timeout value: <' . $timeout . '>. Timeout must be positive number.');
        }

        $this->timeout = $timeout;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }

    public function setPort(int $port): void
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
        foreach ($this->logEntries as $logEntry) {
            echo $logEntry . "\n";
        }
    }

    private function writeLog(string $text): void
    {
        if (! $this->logging) {
            return;
        }

        $this->logEntries[] = '-----' . date('Y-m-d H:i:s') . '-----' . $text . '-----';
    }

    /**
     * @see http://www.iana.org/protocols
     * @see http://www.iana.org/assignments/dns-sec-alg-numbers/dns-sec-alg-numbers.xml
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

    /**
     * @return string[]
     */
    public function getRegistryNameServers(string $tld): array
    {
        return (new DnsNameserver())->getNs($tld);
    }

    public function base32encode(string $input, bool $padding = true): string
    {
        $map = [
            '0',
            '1',
            '2',
            '3',
            '4',
            '5',
            '6',
            '7', //  7
            '8',
            '9',
            'a',
            'b',
            'c',
            'd',
            'e',
            'f', // 15
            'g',
            'h',
            'i',
            'j',
            'k',
            'l',
            'm',
            'n', // 23
            'o',
            'p',
            'q',
            'r',
            's',
            't',
            'u',
            'v', // 31
            '=',  // padding char
        ];

        if (empty($input)) {
            return '';
        }

        $input        = str_split($input);
        $binaryString = '';
        foreach ($input as $iValue) {
            $binaryString .= str_pad(base_convert((string) ord($iValue), 10, 2), 8, '0', STR_PAD_LEFT);
        }

        $fiveBitBinaryArray = str_split($binaryString, 5);
        $base32             = '';
        $i                  = 0;
        while ($i < count($fiveBitBinaryArray)) {
            $base32 .= $map[(int) base_convert(str_pad($fiveBitBinaryArray[$i], 5, '0'), 2, 10)];
            $i++;
        }

        $x = strlen($binaryString) % 40;
        if ($padding && $x !== 0) {
            if ($x === 8) {
                $base32 .= str_repeat($map[32], 6);
            } elseif ($x === 16) {
                $base32 .= str_repeat($map[32], 4);
            } elseif ($x === 24) {
                $base32 .= str_repeat($map[32], 3);
            } elseif ($x === 32) {
                $base32 .= $map[32];
            }
        }

        return $base32;
    }

    public function nsec3hash(string $qname, ?string $salt = null, string $iterations = '-'): string
    {
        $salt            = pack('H*', $salt);
        $iterationsCount = (int) $iterations;
        $toHash          = '';

        foreach (explode('.', strtolower($qname) . '.') as $part) {
            $toHash .= chr(strlen($part)) . $part;
        }

        do {
            $toHash .= $salt;
            $toHash  = sha1($toHash, true);
            $iterationsCount--;
        } while ($iterationsCount >= 0);

        return $this->base32encode($toHash);
    }

    protected function decodeResponse(): ?DnsResponse
    {
        $buffersize = strlen($this->rawBuffer);
        $this->writelog('Read Buffer Size ' . $buffersize);
        if ($buffersize < 12) {
            //throw new dnsException("DNS query return buffer too small");
            return null;
        }

        return $this->prepareResponse();
    }

    protected function closeWithError(string $error): void
    {
        if ($this->socket !== null) {
            @stream_socket_shutdown($this->socket, STREAM_SHUT_RDWR);
            @fclose($this->socket);
        }

        $this->currentState = self::STATE_READY;
        if ($this->cb === null) {
            return;
        }

        $cb = $this->cb;
        $cb(null, $error);
    }

    protected function setStateAwaiting(): void
    {
        $this->currentState    = self::STATE_AWAITING;
        $this->awaitingStarted = microtime(true);
    }

    /**
     * @return mixed[]
     */
    protected function prepareRequestHeaders(string $question, string $type, int $typeId): array
    {
        $labels = [];
        // Split Into Labels
        if (preg_match('/[a-z|A-Z]/', $question) === 0) { // IP Address
            $labeltmp = explode('.', $question);    // reverse ARPA format
            for ($i = count($labeltmp) - 1; $i >= 0; $i--) {
                $labels[] = $labeltmp[$i];
            }

            $labels[] = 'IN-ADDR';
            $labels[] = 'ARPA';
        } else {
            $labels = explode('.', $question);
        }

        $questionBinary = '';
        foreach ($labels as $aValue) {
            $size            = strlen($aValue);
            $questionBinary .= pack('C', $size); // size byte first
            $questionBinary .= $aValue; // then the label
        }

        $questionBinary .= pack('C', 0); // end it off

        $this->writeLog('Question: ' . $question . ' (type=' . $type . '/' . $typeId . ')');

        try {
            $id = random_int(1, 255) | (random_int(0, 255) << 8);
            /** @phpstan-ignore-next-line */
        } catch (Throwable $e) {
            $id = 0;
        } // generate the ID

        // Set standard codes and flags
        $flags = (0x0100 & 0x0300) | 0x0020; // recursion & queryspecmask | authenticated data

        $opcode = 0x0000; // opcode

        // Build the header
        $header        = '';
        $header       .= pack('n', $id);
        $header       .= pack('n', $opcode | $flags);
        $header       .= pack('nnnn', 1, 0, 0, 0);
        $header       .= $questionBinary;
        $header       .= pack('n', $typeId);
        $header       .= pack('n', 0x0001); // internet class
        $headerSize    = strlen($header);
        $headerSizeBin = pack('n', $headerSize);

        $this->writeLog('Header Length: ' . $headerSize . ' Bytes');

        return [$header, $headerSize, $headerSizeBin];
    }

    protected function prepareResponse(): DnsResponse
    {
        $rawHeader = substr($this->rawBuffer, 0, 12); // first 12 bytes is the header
        $header    = unpack('nid/nflags/nqdcount/nancount/nnscount/narcount', $rawHeader);
        $flags     = sprintf("%016b\n", $header['flags']);
        $response  = new DnsResponse();

        $response->setAuthorative($flags[5] === '1');
        $response->setTruncated($flags[6] === '1');
        $response->setRecursionRequested($flags[7] === '1');
        $response->setRecursionAvailable($flags[8] === '1');
        $response->setAuthenticated($flags[10] === '1');
        $response->setDnssecAware($flags[11] === '1');
        $response->setAnswerCount((int) $header['ancount']);

        $this->writeLog('Query returned ' . (string) $header['ancount'] . ' Answers');

        // Deal with the header question data
        if ($header['qdcount'] > 0) {
            $response->setQueryCount((int) $header['qdcount']);
            $this->writeLog('Found ' . (string) $header['qdcount'] . ' questions');
            $q = '';
            for ($a = 0; $a < $header['qdcount']; $a++) {
                $c = 1;
                while ($c !== 0) {
                    $c  = hexdec(bin2hex($response->readResponse($this->rawBuffer, 1)));
                    $q .= $c;
                }

                $response->addQuery($q);
                /** @noinspection UnusedFunctionResultInspection */
                $response->readResponse($this->rawBuffer, 4);
            }
        }

        $this->writeLog('Found ' . (string) $header['ancount'] . ' answer records');
        $response->setResourceResultCount((int) $header['ancount']);
        for ($a = 0; $a < $header['ancount']; $a++) {
            $response->readRecord($this->rawBuffer, DnsResponse::RESULTTYPE_RESOURCE);
        }

        $this->writeLog('Found ' . (string) $header['nscount'] . ' authorative records');
        $response->setNameserverResultCount((int) $header['nscount']);
        for ($a = 0; $a < $header['nscount']; $a++) {
            $response->readRecord($this->rawBuffer, DnsResponse::RESULTTYPE_NAMESERVER);
        }

        $response->setAdditionalResultCount((int) $header['arcount']);
        $this->writeLog('Found ' . (string) $header['arcount'] . ' additional records');
        for ($a = 0; $a < $header['arcount']; $a++) {
            $response->readRecord($this->rawBuffer, DnsResponse::RESULTTYPE_ADDITIONAL);
        }

        return $response;
    }
}
