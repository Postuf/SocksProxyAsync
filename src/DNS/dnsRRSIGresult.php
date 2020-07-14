<?php

/** @noinspection PhpUnused */
/** @noinspection SpellCheckingInspection */

namespace SocksProxyAsync\DNS;

class dnsRRSIGresult extends dnsResult
{
    private const DATE_FORMAT = 'YmdHis';

    private $typecovered;
    private $algorithm;
    private $labels;
    private $originalttl;
    private $expirationdate;
    private $expirationtimestamp;
    private $inceptiondate;
    private $inceptiontimestamp;
    private $keytag;
    private $signername;
    private $signature;
    private $signaturebase64;
    private $publickey;

    /**
     * dnsRRSIGresult constructor.
     *
     * @param $type
     * @param $algorithm
     * @param $labels
     * @param $originalttl
     * @param $expiration
     * @param $inception
     * @param $keytag
     *
     * @throws dnsException
     */
    public function __construct($type, $algorithm, $labels, $originalttl, $expiration, $inception, $keytag)
    {
        parent::__construct();
        $types = new DNSTypes();
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

    public function setExpirationTimestamp($timestamp): void
    {
        $this->expirationtimestamp = $timestamp;
    }

    public function getExpirationTimestamp()
    {
        return $this->expirationtimestamp;
    }

    public function setInceptionTimestamp($timestamp): void
    {
        $this->inceptiontimestamp = $timestamp;
    }

    public function getInceptionTimestamp()
    {
        return $this->inceptiontimestamp;
    }

    public function setSignature($sig): void
    {
        $this->signature = $sig;
    }

    public function getSignature()
    {
        return $this->signature;
    }

    public function setSignatureBase64($sig): void
    {
        $this->signaturebase64 = $sig;
    }

    public function getSignatureBase64()
    {
        return $this->signaturebase64;
    }

    public function setSignername($name): void
    {
        $this->signername = $name;
    }

    public function getSignername()
    {
        return $this->signername;
    }

    public function setTypecovered($type): void
    {
        $this->typecovered = $type;
    }

    public function getTypecovered()
    {
        return $this->typecovered;
    }

    public function setAlgorithm($algorithm): void
    {
        $this->algorithm = $algorithm;
    }

    public function getAlgorithm()
    {
        return $this->algorithm;
    }

    public function setLabels($labels): void
    {
        $this->labels = $labels;
    }

    public function getLabels()
    {
        return $this->labels;
    }

    public function setExpirationDate($expiration): void
    {
        $this->expirationdate = $expiration;
    }

    public function getExpirationDate()
    {
        return $this->expirationdate;
    }

    public function setInceptionDate($inception): void
    {
        $this->inceptiondate = $inception;
    }

    public function getInceptionDate()
    {
        return $this->inceptiondate;
    }

    public function setOriginalTTL($ttl): void
    {
        $this->originalttl = $ttl;
    }

    public function getOriginalTTL()
    {
        return $this->originalttl;
    }

    public function setKeytag($keytag): void
    {
        $this->keytag = $keytag;
    }

    public function getKeytag()
    {
        return $this->keytag;
    }

    public function setPublicKey($key): void
    {
        $this->publickey = $key;
    }

    public function getPublicKey()
    {
        return $this->publickey;
    }
}
