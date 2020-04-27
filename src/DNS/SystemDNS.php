<?php

declare(strict_types=1);

namespace SocksProxyAsync\DNS;

class SystemDNS
{
    private const ETC_RESOLV_CONF = '/etc/resolv.conf';

    /**
     * @return string|null
     */
    public function getSystemDnsHost(): ?string
    {
        if (!file_exists(self::ETC_RESOLV_CONF)) {
            return null;
        }

        $contents = file_get_contents(self::ETC_RESOLV_CONF);
        $lines = explode("\n", $contents);
        foreach ($lines as $line) {
            $line = trim($line);
            if (strpos($line, '#') !== false) {
                $line = substr($line, 0, strpos($line, '#'));
                $line = trim($line);
            }
            if (strpos($line, 'nameserver ') !== false) {
                $line = str_replace('nameserver ', '', $line);

                return trim($line);
            }
        }

        return null;
    }
}
