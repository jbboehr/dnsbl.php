<?php

namespace DNSBL;

class DNSBL
{
    /**     
     * Array of blacklists.
     *
     * Must have one or more elements.
     *
     * @var    array
     * @access protected
     */
    protected $blacklists = array();
    
    protected $dumpFile;
    
    /**
     * Preloaded records
     * 
     * @var array
     */
    protected $preloads = array();
    
    /**
     * Constructor
     * 
     * @param array $spec
     */
    public function __construct($spec)
    {
        if( !empty($spec['blacklists']) ) {
            $this->blacklists = $spec['blacklists'];
        }
        if( !empty($spec['dumpFile']) ) {
            $this->dumpFile = $spec['dumpFile'];
        }
        if( !empty($spec['preloads']) ) {
            $this->preloads = $spec['preloads'];
        } else if( !empty($spec['preloadFile']) && 
                    file_exists($spec['preloadFile']) ) {
            if( substr($spec['preloadFile'], -5) === '.json' ) {
                $this->preloads = json_decode(file_get_contents($spec['preloadFile']), true) ?: array();
            }
        }
    }
    
    /**
     * Magic call override
     * 
     * @param string $m
     * @param mixed $a
     * @throws \DNSBL\Exception
     */
    public function __call($m, $a)
    {
        throw new Exception('Call to undefined method: ' . __CLASS__ . ':' . $m);
    }
    
    /**
     * Set the blacklist to a desired blacklist.
     *
     * @param array $blacklists Array of blacklists to use.
     *
     * @access public
     * @return bool true if the operation was successful
     */
    public function setBlacklists($blacklists)
    {
        if (is_array($blacklists)) {
            $this->blacklists = $blacklists;
            return true;
        } else {
            return false;
        } // if
    } // function

    /**
     * Get the blacklists.
     *
     * @access public
     * @return array Currently set blacklists.
     */
    public function getBlacklists()
    {
        return $this->blacklists;
    }
    
    /**
     * Get details
     * 
     * @param string $host
     * @param boolean $checkall
     * @return \DNSBL\Result
     */
    public function getDetails($host, $checkall = false)
    {
        if( !is_string($host) ) {
            return null;
        }
        
        $results = array();
        foreach ($this->blacklists as $blacklist) {
            $records = $this->getDnsRecord($blacklist, $host);
            $results[$blacklist] = $records;
            if( !empty($records) && !$checkall ) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * Get list of blacklists listing the host
     * 
     * @param string $host
     * @return array
     */
    public function getListingBlacklists($host)
    {
        $blacklists = array();
        $result = $this->getDetails($host, true);
        foreach( $result as $blacklist => $records ) {
            if( !empty($records) ) {
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
     * @param boolean $checkall Iterate through all blacklists and
     *                          return all A records or stop after 
     *                          the first hit?
     *
     * @access public
     * @return boolean true if the checked host is listed in a blacklist.
     */
    public function isListed($host, $checkall = false)
    {
        $result = $this->getDetails($host, $checkall);
        if( !is_array($result) ) {
            return false;
        }
        foreach( $result as $records ) {
            if( !empty($records) ) {
                return true;
            }
        }
        return false;
    } // function
    
    /**
     * Get a DNS record
     * 
     * @param string $blacklist
     * @param string $host
     */
    protected function getDnsRecord($blacklist, $host)
    {
        if( array_key_exists($host, $this->preloads) &&
            array_key_exists($blacklist, $this->preloads[$host]) ) {
            return $this->preloads[$host][$blacklist];
        }
        
        $hostForLookup = $this->getHostForLookup($host, $blacklist);
        $records = dns_get_record($hostForLookup, DNS_A | DNS_TXT);
        
        if( $this->dumpFile ) {
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
     *
     * @access protected
     * @return string Ready to use host to lookup
     */    
    protected function getHostForLookup($host, $blacklist) 
    {
        // Currently only works for v4 addresses.
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ip = $host;
        } else {
            $ip = gethostbyname($host);
        }
        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            return;
        }

        return $this->buildLookUpHost($ip, $blacklist);
    } // function

    /**
     * Build the host to lookup from an IP.
     *
     * @param string $ip        IP to use for building the lookup.
     * @param string $blacklist Blacklist to use for building the lookup.
     *
     * @access protected
     * @return string Ready to use host to lookup
     */    
    protected function buildLookUpHost($ip, $blacklist)
    {
        return $this->reverseIp($ip).'.'.$blacklist;        
    } // function

    /**
     * Reverse the order of an IP. 127.0.0.1 -> 1.0.0.127. Currently
     * only works for v4-adresses
     *
     * @param string $ip IP address to reverse.
     *
     * @access protected
     * @return string Reversed IP
     */    
    protected function reverseIp($ip) 
    {        
        return implode('.', array_reverse(explode('.', $ip)));        
    } // function
    
    /**
     * Update the dump file with the specified records
     * 
     * @param string $host
     * @param string $blacklist
     * @param array $records
     * @return void
     */
    protected function updateDumpFile($host, $blacklist, $records)
    {
        $fh = fopen($this->dumpFile, 'c+');
        if( !flock($fh, LOCK_EX) ) {
            fclose($fh);
            return;
        }
        
        $buf = stream_get_contents($fh);
        
        $data = (json_decode($buf, true) ?: array());
        $data[$host][$blacklist] = $records;
        $newbuf = json_encode($data, defined('JSON_PRETTY_PRINT') ? JSON_PRETTY_PRINT : 0);
        
        ftruncate($fh, 0);
        fseek($fh, 0);
        fwrite($fh, $newbuf);
        flock($fh, LOCK_UN);
        fclose($fh);
        
    } // function
} // class
