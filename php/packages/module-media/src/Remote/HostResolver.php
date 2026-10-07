<?php

declare(strict_types=1);

namespace WebxUi\Media\Remote;

/**
 * The addresses a host name stands for, both families.
 *
 * Its own class so that a test can answer for a name without a network; {@see RemoteFetcher}
 * checks every address this returns and then connects to one of them and nothing else.
 */
class HostResolver
{
    /**
     * @return list<string>
     */
    public function resolve(string $host): array
    {
        $addresses = [];

        // The system resolver first: it reads the hosts file, which is exactly how the site's own
        // name or `localhost` turns into a loopback address a DNS query would never return.
        $v4 = gethostbynamel($host);

        if (is_array($v4)) {
            array_push($addresses, ...$v4);
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ip']) && is_string($record['ip'])) {
                $addresses[] = $record['ip'];
            }

            if (isset($record['ipv6']) && is_string($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }
}
