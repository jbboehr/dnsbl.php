<?php

namespace DNSBL;

/**
 * @phpstan-type DnsRecordsArray list<array<string, mixed>>
 * @psalm-type DnsRecordsArray = list<array<string, mixed>>
 */
class DNSBL
{
    /**
     * Array of blacklists.
     *
     * Must have one or more elements.
     *
     * @var list<string>
     */
    protected $blacklists = [];

    /** @var ?string */
    protected $dumpFile = null;

    /**
     * Preloaded records
     *
     * @psalm-suppress UndefinedDocblockClass
     * @var array
     * @phpstan-var array<string, array<string, DnsRecordsArray>>
     * @psalm-var array<string, array<string, DnsRecordsArray>>
     */
    protected $preloads = [];

    /**
     * Constructor
     *
     * @psalm-suppress MixedAssignment
     * @param array $spec
     * @phpstan-param array{
 *           blacklists?: list<string>,
     *       dumpFile?: string,
     *       preloads?: array<string, array<string, DnsRecordsArray>>,
     *       preloadFile?: string,
     *     } $spec
     */
    public function __construct(array $spec)
    {
        if (isset($spec['blacklists'])) {
            $this->blacklists = $spec['blacklists'];
        }
        if (isset($spec['dumpFile'])) {
            $this->dumpFile = $spec['dumpFile'];
        }
        if (isset($spec['preloads'])) {
            $this->preloads = $spec['preloads'];
        } else {
            $preloadFile = $spec['preloadFile'] ?? null;
            if (null !== $preloadFile && file_exists($preloadFile)) {
                /**
                 * @psalm-suppress RiskyTruthyFalsyComparison
                 **/
                $tmp = json_decode(file_get_contents($preloadFile) ?: '[]', true) ?: [];
                $this->preloads = $tmp; // @phpstan-ignore-line
            }
        }
    }

    /**
     * Set the blacklist to a desired blacklist.
     *
     * @param list<string> $blacklists Array of blacklists to use.
     */
    public function setBlacklists(array $blacklists): bool
    {
        $this->blacklists = $blacklists;
        return true;
    }

    /**
     * Get the blacklists.
     *
     * @return list<string>
     */
    public function getBlacklists(): array
    {
        return $this->blacklists;
    }

    /**
     * Get details
     *
     * @param string $host
     * @param boolean $check_all
     * @return array
     * @phpstan-return array<string, ?DnsRecordsArray>
     */
    public function getDetails(string $host, bool $check_all = false): array
    {
        $results = [];
        foreach ($this->blacklists as $blacklist) {
            $records = $this->getDnsRecord($blacklist, $host);
            $results[$blacklist] = $records;
            if (null !== $records && count($records) > 0 && !$check_all) {
                break;
            }
        }

        return $results;
    }

    /**
     * Get list of blacklists listing the host
     *
     * @return list<string>
     */
    public function getListingBlacklists(string $host): array
    {
        $blacklists = array();
        $result = $this->getDetails($host, true);
        foreach ($result as $blacklist => $records) {
            if (null !== $records && count($records) > 0) {
                $blacklists[] = $blacklist;
            }
        }
        return $blacklists;
    }

    /**
     * Checks if the supplied Host is listed in one or more of the
     * RBLs.
     *
     * @param string  $host     Host to check for being listed.
     * @param boolean $check_all Iterate through all blacklists and
     *                          return all A records or stop after
     *                          the first hit?
     * @return boolean true if the checked host is listed in a blacklist.
     */
    public function isListed(string $host, bool $check_all = false): bool
    {
        $result = $this->getDetails($host, $check_all);
        foreach ($result as $records) {
            if ($records !== null && count($records) > 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a DNS record
     *
     * @return ?DnsRecordsArray
     */
    protected function getDnsRecord(string $blacklist, string $host): ?array
    {
        if (
            array_key_exists($host, $this->preloads) &&
            array_key_exists($blacklist, $this->preloads[$host])
        ) {
            return $this->preloads[$host][$blacklist];
        }

        $hostForLookup = $this->getHostForLookup($host, $blacklist);
        if (null === $hostForLookup) {
            return null;
        }

        $records = dns_get_record($hostForLookup, DNS_A | DNS_TXT);
        if ($records === false) {
            $records = null;
        }
        /** @var ?DnsRecordsArray $records */

        if ($this->dumpFile !== null) {
            $this->updateDumpFile($host, $blacklist, $records);
        }

        return $records;
    }

    /**
     * Get host to lookup. Lookup a host if neccessary and get the
     * complete FQDN to lookup.
     *
     * @param string $host      Host OR IP to use for building the lookup.
     * @param string $blacklist Blacklist to use for building the lookup.
     * @return string Ready to use host to lookup
     */
    protected function getHostForLookup(string $host, string $blacklist): ?string
    {
        // Currently only works for v4 addresses.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
        }
        if (/*!$ip ||*/ filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        return $this->buildLookUpHost($ip, $blacklist);
    }

    /**
     * Build the host to lookup from an IP.
     *
     * @param string $ip        IP to use for building the lookup.
     * @param string $blacklist Blacklist to use for building the lookup.
     * @return string Ready to use host to lookup
     */
    protected function buildLookUpHost(string $ip, string $blacklist): string
    {
        return $this->reverseIp($ip) . '.' . $blacklist;
    }

    /**
     * Reverse the order of an IP. 127.0.0.1 -> 1.0.0.127. Currently
     * only works for v4-adresses
     *
     * @param string $ip IP address to reverse.
     *
     * @access protected
     * @return string Reversed IP
     */
    protected function reverseIp(string $ip): string
    {
        return implode('.', array_reverse(explode('.', $ip)));
    }

    /**
     * Update the dump file with the specified records
     *
     * @phpstan-param ?DnsRecordsArray $records
     */
    protected function updateDumpFile(string $host, string $blacklist, ?array $records): void
    {
        assert($this->dumpFile !== null);

        $fh = fopen($this->dumpFile, 'c+');
        if ($fh === false) {
            return;
        }

        if (!flock($fh, LOCK_EX)) {
            fclose($fh);
            return;
        }

        $buf = stream_get_contents($fh);
        if ($buf === false) {
            fclose($fh);
            return;
        }

        /** @var array<string, array<string, DnsRecordsArray>> $data */
        $data = (json_decode($buf, true) ?: []);
        $data[$host][$blacklist] = $records;
        $newbuf = json_encode($data, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
        if ($newbuf === false) {
            fclose($fh);
            return;
        }

        ftruncate($fh, 0);
        fseek($fh, 0);
        fwrite($fh, $newbuf);
        flock($fh, LOCK_UN);
        fclose($fh);
    }
}
