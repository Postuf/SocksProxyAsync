<?php

/** @noinspection SpellCheckingInspection */
declare(strict_types=1);

namespace SocksProxyAsync\DNS;

/**
 * Reference http://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml.
 */
class dnsTypes
{
    /** @var string[] */
    public static $typesById = [];
    /** @var int[] */
    public static $typesByName = [];

    private static function addType(int $id, string $name)
    {
        self::$typesById[$id] = $name;
        self::$typesByName[$name] = $id;
    }

    /**
     * @see https://tools.ietf.org/html/rfc1035 A, NS, CNAME, ...
     * @see https://tools.ietf.org/html/rfc2308 SOA
     * @see https://tools.ietf.org/html/rfc7505 MX
     * @see https://tools.ietf.org/html/rfc1183 RP
     * @see https://tools.ietf.org/html/rfc5864 AFSDB
     * @see https://tools.ietf.org/html/draft-ietf-dane-openpgpkey-12
     */
    public function __construct()
    {
        if (!self::$typesById) {
            self::addType(1, 'A'); // RFC1035
            self::addType(2, 'NS'); // RFC1035
            self::addType(5, 'CNAME'); // RFC1035
            self::addType(6, 'SOA'); // RFC1035 RFC2308
            self::addType(12, 'PTR'); // RFC1035
            self::addType(13, 'HINFO');
            self::addType(14, 'MINFO');
            self::addType(15, 'MX'); // RFC1035 RFC7505
            self::addType(16, 'TXT'); // RFC1035
            self::addType(17, 'RP'); // RFC1183
            self::addType(18, 'AFSDB'); // RFC1183 RFC5864
            self::addType(19, 'X25'); // RFC1183
            self::addType(20, 'ISDN'); // RFC1183
            self::addType(21, 'RT'); // RFC1183
            self::addType(22, 'NSAP'); // RFC1706
            self::addType(23, 'NSAP-PTR'); // RFC1348 RFC1637 RFC1706
            self::addType(24, 'SIG'); // RFC4034 RFC3755 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
            self::addType(25, 'KEY'); // RFC2930 RFC4034 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
            self::addType(26, 'PX'); // RFC2136
            self::addType(27, 'GPOS'); // RFC1712
            self::addType(28, 'AAAA'); // RFC3596
            self::addType(29, 'LOC'); // RFC1876
            self::addType(31, 'EID');
            self::addType(32, 'NIMLOC');
            self::addType(33, 'SRV'); // RFC2782
            self::addType(34, 'ATMA');
            self::addType(35, 'NAPTR'); // RFC3403
            self::addType(36, 'KX'); // RFC2230
            self::addType(37, 'CERT'); // RFC4398
            self::addType(39, 'DNAME'); // RFC2672
            self::addType(40, 'SINK');
            self::addType(41, 'OPT'); // RFC6891 RFC3658
            self::addType(42, 'APL');
            self::addType(43, 'DS'); // RFC4034 RFC3658
            self::addType(44, 'SSHFP'); // RFC4255
            self::addType(45, 'IPSECKEY'); // RFC4025
            self::addType(46, 'RRSIG'); // RFC4034 RFC3755
            self::addType(47, 'NSEC'); // RFC4034 RFC3755
            self::addType(48, 'DNSKEY'); // RFC4034 RFC3755
            self::addType(49, 'DHCID'); // RFC4701
            self::addType(50, 'NSEC3'); // RFC5155
            self::addType(51, 'NSEC3PARAM'); // RFC5155
            self::addType(52, 'TLSA'); // RFC6698
            self::addType(55, 'HIP'); // RFC5205
            self::addType(56, 'NINFO');
            self::addType(57, 'RKEY');
            self::addType(58, 'TALINK');
            self::addType(59, 'CDS'); // RFC7344
            self::addType(60, 'CDNSKEY'); // RFC7344
            self::addType(61, 'OPENPGPKEY'); // internet draft
            self::addType(62, 'CSYNC'); // RFC7477
            self::addType(99, 'SPF'); // RFC4408 RFC7208
            self::addType(100, 'UNIFO'); // IANA Reserved
            self::addType(101, 'UID'); // IANA Reserved
            self::addType(102, 'GID'); // IANA Reserved
            self::addType(103, 'UNSPEC'); // IANA Reserved
            self::addType(104, 'NID'); // RFC6742
            self::addType(105, 'L32'); // RFC6742
            self::addType(106, 'L64'); // RFC6742
            self::addType(107, 'LP'); // RFC6742
            self::addType(108, 'EUI48'); // RFC7043
            self::addType(109, 'EUI64'); // RFC7043
            self::addType(249, 'TKEY'); // RFC2930
            self::addType(250, 'TSIG'); // RFC2845
            self::addType(251, 'IXFR'); // RFC1995
            self::addType(252, 'AXFR'); // RFC1035 RFC5936
            self::addType(253, 'MAILB'); // RFC1035
            self::addType(254, 'MAILA'); // RFC1035
            self::addType(255, 'ANY'); // RFC1035 RFC6895
            self::addType(256, 'URI'); // RFC7553
            self::addType(257, 'CAA'); // RFC6844
            self::addType(32768, 'TA');
            self::addType(32769, 'DLV');
            self::addType(65534, 'TYPE65534'); // Eurid uses this one?
        }
    }

    /**
     * @param string $name
     *
     * @throws dnsException
     *
     * @return int
     */
    public function getByName(string $name): int
    {
        if (isset(self::$typesByName[$name])) {
            return self::$typesByName[$name];
        } else {
            throw new dnsException("Invalid name $name specified on getByName");
        }
    }

    /**
     * @param int $id
     *
     * @throws dnsException
     *
     * @return string
     */
    public function getById(int $id): string
    {
        if (isset(self::$typesById[$id])) {
            return self::$typesById[$id];
        } else {
            throw new dnsException("Invalid id $id on getById");
        }
    }
}
