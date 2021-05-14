<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

use function base64_encode;
use function implode;
use function ord;
use function sprintf;
use function substr;
use function unpack;
use function strlen;
use function strtoupper;

/** @psalm-suppress PropertyNotSetInConstructor */
final class DnsResponse
{
    private const C64K                 = 65536;
    public const RESULTTYPE_RESOURCE   = 'resource';
    public const RESULTTYPE_NAMESERVER = 'nameserver';
    public const RESULTTYPE_ADDITIONAL = 'additional';

    protected int $responsecounter;
    /** @var DnsResult[] */
    protected array $resourceResults;
    /** @var DnsResult[] */
    protected array $nameserverResults;
    /** @var DnsResult[] */
    protected array $additionalResults;
    protected int $resourceResponses;
    protected int $nameserverResponses;
    protected int $additionalResponses;
    /** @var string[] */
    protected array $queries;
    private int $questions;
    private int $answers;
    private bool $authorative;
    private bool $truncated;
    private bool $recursionRequested;
    private bool $recursionAvailable;
    private bool $authenticated;
    private bool $dnssecAware;

    public function __construct()
    {
        $this->authorative        = false;
        $this->truncated          = false;
        $this->recursionRequested = false;
        $this->recursionAvailable = false;
        $this->authenticated      = false;
        $this->dnssecAware        = false;
        $this->responsecounter    = 12;
        $this->queries            = [];
        $this->resourceResults    = [];
        $this->nameserverResults  = [];
        $this->additionalResults  = [];
    }

    public function addResult(DnsResult $result, string $recordtype): void
    {
        switch ($recordtype) {
            case self::RESULTTYPE_RESOURCE:
                $this->resourceResults[] = $result;
                break;
            case self::RESULTTYPE_NAMESERVER:
                $this->nameserverResults[] = $result;
                break;
            case self::RESULTTYPE_ADDITIONAL:
                $this->additionalResults[] = $result;
                break;
            default:
                break;
        }
    }

    public function addQuery(string $query): void
    {
        $this->queries[] = $query;
    }

    /**
     * @return string[]
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    public function setAnswerCount(int $count): void
    {
        $this->answers = $count;
    }

    public function getAnswerCount(): int
    {
        return $this->answers;
    }

    public function setQueryCount(int $count): void
    {
        $this->questions = $count;
    }

    public function getQueryCount(): int
    {
        return $this->questions;
    }

    public function setAuthorative(bool $flag): void
    {
        $this->authorative = $flag;
    }

    public function getAuthorative(): bool
    {
        return $this->authorative;
    }

    public function setTruncated(bool $flag): void
    {
        $this->truncated = $flag;
    }

    public function getTruncated(): bool
    {
        return $this->truncated;
    }

    public function setRecursionRequested(bool $flag): void
    {
        $this->recursionRequested = $flag;
    }

    public function getRecursionRequested(): bool
    {
        return $this->recursionRequested;
    }

    public function setRecursionAvailable(bool $flag): void
    {
        $this->recursionAvailable = $flag;
    }

    public function getRecursionAvailable(): bool
    {
        return $this->recursionAvailable;
    }

    public function setAuthenticated(bool $flag): void
    {
        $this->authenticated = $flag;
    }

    public function getAuthenticated(): bool
    {
        return $this->authenticated;
    }

    public function setDnssecAware(bool $flag): void
    {
        $this->dnssecAware = $flag;
    }

    public function getDnssecAware(): bool
    {
        return $this->dnssecAware;
    }

    /**
     * @return DnsResult[]
     */
    public function getResourceResults(): array
    {
        return $this->resourceResults;
    }

    /**
     * @return DnsResult[]
     */
    public function getNameserverResults(): array
    {
        return $this->nameserverResults;
    }

    /**
     * @return DnsResult[]
     */
    public function getAdditionalResults(): array
    {
        return $this->additionalResults;
    }

    public function readResponse(string $buffer, int $count = 1, ?int $offset = null): string
    {
        if ($offset === null) { // no offset so use and increment the ongoing counter
            $return                 = substr($buffer, $this->responsecounter, $count);
            $this->responsecounter += $count;
        } else {
            $return = substr($buffer, $offset, $count);
        }

        return $return;
    }

    /**
     * @noinspection TypeUnsafeComparisonInspection
     */
    public function readRecord(string $buffer, string $resulttype = ''): void
    {
        $domain       = $this->readDomainLabel($buffer);
        $ansHeaderBin = $this->readResponse($buffer, 10); // 10 byte header
        $ansHeader    = unpack('ntype/nclass/Nttl/nlength', $ansHeaderBin);
        $typeId       = (new DnsTypes())->getById((int) $ansHeader['type']);
        switch ($typeId) {
            case 'A':
                $result = new DnsAResult(implode('.', unpack('Ca/Cb/Cc/Cd', $this->readResponse($buffer, 4))));
                break;

            case 'NS':
                $result = new DnsNSResult($this->readDomainLabel($buffer));
                break;

            case 'PTR':
                $result = new DnsPTRresult($this->readDomainLabel($buffer));
                break;

            case 'CNAME':
                $result = new DnsCNAMEResult($this->readDomainLabel($buffer));
                break;

            case 'MX':
                $result = new DnsMXResult();
                $prefs  = $this->readResponse($buffer, 2);
                $prefs  = unpack('nprio', $prefs);
                $result->setPrio((int) $prefs['prio']);
                $result->setServer($this->readDomainLabel($buffer));
                break;

            case 'SOA':
                $result = new DnsSOAResult();
                $result->setNameserver($this->readDomainLabel($buffer));
                $result->setResponsible($this->readDomainLabel($buffer));
                $buffer = $this->readResponse($buffer, 20);
                $extras = unpack('Nserial/Nrefresh/Nretry/Nexpiry/Nminttl', $buffer);
                $result->setSerial((int) $extras['serial']);
                $result->setRefresh((int) $extras['refresh']);
                $result->setRetry((int) $extras['retry']);
                $result->setExpiry((int) $extras['expiry']);
                $result->setMinttl((int) $extras['minttl']);
                break;

            case 'TXT':
                $result = new DnsTXTResult($this->readResponse($buffer, (int) $ansHeader['length']));
                break;

            case 'DS':
                $stuff           = $this->readResponse($buffer, (int) $ansHeader['length']);
                $length          = (((int) $ansHeader['length'] - 4) * 2) - 8;
                $stuff           = unpack('nkeytag/Calgo/Cdigest/H' . $length . 'string/H*rest', $stuff);
                $stuff['string'] = strtoupper((string) $stuff['string']);
                $stuff['rest']   = strtoupper((string) $stuff['rest']);
                $result          = new DnsDSResult((int) $stuff['keytag'], (string) $stuff['algo'], (string) $stuff['digest'], $stuff['string'], $stuff['rest']);
                break;

            case 'DNSKEY':
                $stuff = $this->readResponse($buffer, (int) $ansHeader['length']);
                $this->keytag($stuff, (int) $ansHeader['length']);
                $this->keytag2($stuff, (int) $ansHeader['length']);
                $extras = unpack('nflags/Cprotocol/Calgorithm/a*pubkey', $stuff);
                $flags  = sprintf("%016b\n", $extras['flags']);
                $result = new DnsDNSKEYResult((int) $extras['flags'], (string) $extras['protocol'], (string) $extras['algorithm'], (string) $extras['pubkey']);
                $result->setKeytag($this->keytag($stuff, (int) $ansHeader['length']));
                if ($flags[7] === '1') {
                    $result->setZoneKey(true);
                }

                if ($flags[15] === '1') {
                    $result->setSep(true);
                }

                break;

            case 'RRSIG':
                $stuff  = $this->readResponse($buffer, 18);
                $test   = unpack('ntype/calgorithm/clabels/Noriginalttl/Nexpiration/Ninception/nkeytag', $stuff);
                $result = new DnsRRSIGResult((int) $test['type'], (string) $test['algorithm'], (string) $test['labels'], (int) $test['originalttl'], (int) $test['expiration'], (int) $test['inception'], (int) $test['keytag']);
                $name   = $this->readDomainLabel($buffer);
                $result->setSignername($name);
                $sig = $this->readResponse($buffer, (int) $ansHeader['length'] - (strlen($name) + 2) - 18);
                $result->setSignature($sig);
                $result->setSignatureBase64(base64_encode($sig));
                break;

            default: // something we can't deal with
                $result = new DnsResult();
                $stuff  = $this->readResponse($buffer, (int) $ansHeader['length']);
                $result->setData($stuff);
                break;
        }

        $result->setDomain($domain);
        $result->setType((int) $ansHeader['type']);
        $result->setTypeId($typeId);
        $result->setClass((int) $ansHeader['class']);
        $result->setTtl((int) $ansHeader['ttl']);
        $this->addResult($result, $resulttype);
    }

    /** @psalm-suppress MixedOperand */
    private function keytag(string $key, int $keysize): int
    {
        $ac = 0;
        for ($i = 0; $i < $keysize; $i++) {
            $keyp = unpack('C', $key[$i]);
            $ac  += ($i & 1 ? $keyp[1] : $keyp[1] << 8);
        }

        $ac += ($ac >> 16) & 0xFFFF;

        return (int) ($ac & 0xFFFF);
    }

    /** @psalm-suppress MixedOperand */
    private function keytag2(string $key, int $keysize): int
    {
        $ac = 0;
        for ($i = 0; $i < $keysize; $i++) {
            $keyp = unpack('C', $key[$i]);
            $ac  += ($i % 2 === 1 ? $keyp[1] : 256 * $keyp[1]);
        }

        $ac += ($ac / self::C64K) % self::C64K;

        return (int) ($ac % self::C64K);
    }

    private function readDomainLabel(string $buffer): string
    {
        $count                  = 0;
        $labels                 = $this->readDomainLabels($buffer, $this->responsecounter, $count);
        $domain                 = implode('.', $labels);
        $this->responsecounter += $count;

        return $domain;
    }

    /**
     * @return string[]
     */
    private function readDomainLabels(string $buffer, int $offset, int &$counter = 0): array
    {
        $labels      = [];
        $startoffset = $offset;
        $return      = false;
        while (! $return) {
            $labelLen = ord($this->readResponse($buffer, 1, $offset++));
            if ($labelLen <= 0) {
                $return = true;
                // end of data
            } elseif ($labelLen < 64) { // uncompressed data
                $labels[] = $this->readResponse($buffer, $labelLen, $offset);
                $offset  += $labelLen;
            } else { // labelLen>=64 -- pointer
                $nextitem      = $this->readResponse($buffer, 1, $offset++);
                $pointerOffset = (($labelLen & 0x3f) << 8) + ord($nextitem);
                // Branch Back Upon Ourselves...
                foreach ($this->readDomainLabels($buffer, $pointerOffset) as $ptrLabel) {
                    $labels[] = $ptrLabel;
                }

                $return = true;
            }
        }

        $counter = (int) ($offset - $startoffset);

        return $labels;
    }

    public function setResourceResultCount(int $count): void
    {
        $this->resourceResponses = $count;
    }

    public function getResourceResultCount(): int
    {
        return $this->resourceResponses;
    }

    public function setNameserverResultCount(int $count): void
    {
        $this->nameserverResponses = $count;
    }

    public function getNameserverResultCount(): int
    {
        return $this->nameserverResponses;
    }

    public function setAdditionalResultCount(int $count): void
    {
        $this->additionalResponses = $count;
    }

    public function getAdditionalResultCount(): int
    {
        return $this->additionalResponses;
    }
}
