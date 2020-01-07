<?php /** @noinspection SpellCheckingInspection */

namespace SocksProxyAsync\DNS;

/**
 * Reference http://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml
 */
class dnsTypes
{
    /** @var array */
    var $typesById = [];
    /** @var array */
    var $typesByName = [];

    /**
     * @param int $id
     * @param string $name
     */
    private function addType($id, $name)
    {
        $this->typesById[$id] = $name;
        $this->typesByName[$name] = $id;
    }

    function __construct()
    {
        $this->addType(1, "A"); // RFC1035
        $this->addType(2, "NS"); // RFC1035
        $this->addType(5, "CNAME"); // RFC1035
        $this->addType(6, "SOA"); // RFC1035 RFC2308
        $this->addType(12, "PTR"); // RFC1035
        $this->addType(13, "HINFO");
        $this->addType(14, "MINFO");
        $this->addType(15, "MX"); // RFC1035 RFC7505
        $this->addType(16, "TXT"); // RFC1035
        $this->addType(17, "RP"); // RFC1183
        $this->addType(18, "AFSDB"); // RFC1183 RFC5864
        $this->addType(19, "X25"); // RFC1183
        $this->addType(20, "ISDN"); // RFC1183
        $this->addType(21, "RT"); // RFC1183
        $this->addType(22, "NSAP"); // RFC1706
        $this->addType(23, "NSAP-PTR"); // RFC1348 RFC1637 RFC1706
        $this->addType(24, "SIG"); // RFC4034 RFC3755 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
        $this->addType(25, "KEY"); // RFC2930 RFC4034 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
        $this->addType(26, "PX"); // RFC2136
        $this->addType(27, "GPOS"); // RFC1712
        $this->addType(28, "AAAA"); // RFC3596
        $this->addType(29, "LOC"); // RFC1876
        $this->addType(31, "EID");
        $this->addType(32, "NIMLOC");
        $this->addType(33, "SRV"); // RFC2782
        $this->addType(34, "ATMA");
        $this->addType(35, "NAPTR"); // RFC3403
        $this->addType(36, "KX"); // RFC2230
        $this->addType(37, "CERT"); // RFC4398
        $this->addType(39, "DNAME"); // RFC2672
        $this->addType(40, "SINK");
        $this->addType(41, "OPT"); // RFC6891 RFC3658
        $this->addType(42, "APL");
        $this->addType(43, "DS"); // RFC4034 RFC3658
        $this->addType(44, "SSHFP"); // RFC4255
        $this->addType(45, "IPSECKEY"); // RFC4025
        $this->addType(46, "RRSIG"); // RFC4034 RFC3755
        $this->addType(47, "NSEC"); // RFC4034 RFC3755
        $this->addType(48, "DNSKEY"); // RFC4034 RFC3755
        $this->addType(49, "DHCID"); // RFC4701
        $this->addType(50, "NSEC3"); // RFC5155
        $this->addType(51, "NSEC3PARAM"); // RFC5155
        $this->addType(52, "TLSA"); // RFC6698
        $this->addType(55, "HIP"); // RFC5205
        $this->addType(56, "NINFO");
        $this->addType(57, "RKEY");
        $this->addType(58, "TALINK");
        $this->addType(59, "CDS"); // RFC7344
        $this->addType(60, "CDNSKEY"); // RFC7344
        $this->addType(61, "OPENPGPKEY"); // internet draft
        $this->addType(62, "CSYNC"); // RFC7477
        $this->addType(99, "SPF"); // RFC4408 RFC7208
        $this->addType(100, "UNIFO"); // IANA Reserved
        $this->addType(101, "UID"); // IANA Reserved
        $this->addType(102, "GID"); // IANA Reserved
        $this->addType(103, "UNSPEC"); // IANA Reserved
        $this->addType(104, "NID"); // RFC6742
        $this->addType(105, "L32"); // RFC6742
        $this->addType(106, "L64"); // RFC6742
        $this->addType(107, "LP"); // RFC6742
        $this->addType(108, "EUI48"); // RFC7043
        $this->addType(109, "EUI64"); // RFC7043
        $this->addType(249, "TKEY"); // RFC2930
        $this->addType(250, "TSIG"); // RFC2845
        $this->addType(251, "IXFR"); // RFC1995
        $this->addType(252, "AXFR"); // RFC1035 RFC5936
        $this->addType(253, "MAILB"); // RFC1035
        $this->addType(254, "MAILA"); // RFC1035
        $this->addType(255, "ANY"); // RFC1035 RFC6895
        $this->addType(256, "URI"); // RFC7553
        $this->addType(257, "CAA"); // RFC6844
        $this->addType(32768, "TA");
        $this->addType(32769, "DLV");
        $this->addType(65534, "TYPE65534"); // Eurid uses this one?
    }

    /**
     * @param string $name
     * @return int
     * @throws dnsException
     */
    function getByName($name)
    {
        if (isset($this->typesByName[$name])) {
            return $this->typesByName[$name];
        } else {
            throw new dnsException("Invalid name $name specified on getByName");
        }

    }

    /**
     * @param int $id
     * @return string
     * @throws dnsException
     */
    public function getById($id)
    {
        if (isset($this->typesById[$id])) {
            return $this->typesById[$id];
        } else {
            throw new dnsException("Invalid id $id on getById");
        }
    }
}