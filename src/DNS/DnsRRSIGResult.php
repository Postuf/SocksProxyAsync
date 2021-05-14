<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

use function date;

/** @psalm-suppress PropertyNotSetInConstructor */
class DnsRRSIGResult extends DnsResult
{
    private const DATE_FORMAT = 'YmdHis';

    private string $typecovered;
    private string $algorithm;
    private string $labels;
    private int $originalttl;
    private string $expirationdate;
    private int $expirationtimestamp;
    private string $inceptiondate;
    private int $inceptiontimestamp;
    private int $keytag;
    private string $signername;
    private string $signature;
    private string $signaturebase64;
    private string $publickey;

    public function __construct(int $type, string $algorithm, string $labels, int $originalttl, int $expiration, int $inception, int $keytag)
    {
        parent::__construct();
        $types = new DnsTypes();
        $this->setTypecovered($types->getById($type));
        $this->setAlgorithm($algorithm);
        $this->setLabels($labels);
        $this->setOriginalTTL($originalttl);
        $this->setExpirationTimestamp($expiration);
        $this->setInceptionTimestamp($inception);
        $this->setExpirationDate(date(self::DATE_FORMAT, $expiration));
        $this->setInceptionDate(date(self::DATE_FORMAT, $inception));
        $this->setKeytag($keytag);
    }

    public function getTypecovered(): string
    {
        return $this->typecovered;
    }

    public function setTypecovered(string $typecovered): void
    {
        $this->typecovered = $typecovered;
    }

    public function getAlgorithm(): string
    {
        return $this->algorithm;
    }

    public function setAlgorithm(string $algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function getLabels(): string
    {
        return $this->labels;
    }

    public function setLabels(string $labels): void
    {
        $this->labels = $labels;
    }

    public function getOriginalttl(): int
    {
        return $this->originalttl;
    }

    public function setOriginalttl(int $originalttl): void
    {
        $this->originalttl = $originalttl;
    }

    public function getExpirationdate(): string
    {
        return $this->expirationdate;
    }

    public function setExpirationdate(string $expirationdate): void
    {
        $this->expirationdate = $expirationdate;
    }

    public function getExpirationtimestamp(): int
    {
        return $this->expirationtimestamp;
    }

    public function setExpirationtimestamp(int $expirationtimestamp): void
    {
        $this->expirationtimestamp = $expirationtimestamp;
    }

    public function getInceptiondate(): string
    {
        return $this->inceptiondate;
    }

    public function setInceptiondate(string $inceptiondate): void
    {
        $this->inceptiondate = $inceptiondate;
    }

    public function getInceptiontimestamp(): int
    {
        return $this->inceptiontimestamp;
    }

    public function setInceptiontimestamp(int $inceptiontimestamp): void
    {
        $this->inceptiontimestamp = $inceptiontimestamp;
    }

    public function getKeytag(): int
    {
        return $this->keytag;
    }

    public function setKeytag(int $keytag): void
    {
        $this->keytag = $keytag;
    }

    public function getSignername(): string
    {
        return $this->signername;
    }

    public function setSignername(string $signername): void
    {
        $this->signername = $signername;
    }

    public function getSignature(): string
    {
        return $this->signature;
    }

    public function setSignature(string $signature): void
    {
        $this->signature = $signature;
    }

    public function getSignaturebase64(): string
    {
        return $this->signaturebase64;
    }

    public function setSignaturebase64(string $signaturebase64): void
    {
        $this->signaturebase64 = $signaturebase64;
    }

    public function getPublickey(): string
    {
        return $this->publickey;
    }

    public function setPublickey(string $publickey): void
    {
        $this->publickey = $publickey;
    }
}
