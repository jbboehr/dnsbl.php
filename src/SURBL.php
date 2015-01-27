<?php

namespace DNSBL;

class SURBL extends DNSBL
{

    /**     
     * Array of blacklists.
     *
     * Must have one or more elements.
     *
     * @var    string[]
     * @access protected
     */
     protected $blacklists = array();

    /**
     * File containing whitelisted hosts.
     *
     * There are some whitelisted hosts (co.uk for example). This
     * requires the package to not ask the domain name but the host
     * name (spammer.co.uk instead of co.uk).
     * 
     * @var    string
     * @see    $twoLevelCcTld
     * @access protected
     */
    protected $doubleCcTldFile;
    
    private $doubleCcTlds;
    
    /**
     * Constructor
     * 
     * @param array $spec
     */
    public function __construct($spec)
    {
        parent::__construct($spec);
        
        if( !empty($spec['doubleCcTldFile']) ) {
            $this->doubleCcTldFile = $spec['doubleCcTldFile'];
        } else {
            $this->doubleCcTldFile = realpath(__DIR__ . '/../res/two-level-tlds.php');
        }
    }

    /**
     * Check if the last two parts of the FQDN are whitelisted.
     *
     * @param string $fqdn Host to check if it is whitelisted.
     *
     * @access protected
     * @return boolean True if the host is whitelisted
     */
    protected function isDoubleCcTld($fqdn)
    {
        // Load database
        if( !$this->doubleCcTlds ) {
            if( 0 === stripos($this->doubleCcTldFile, 'http') ) {
                // It's a url
                $raw = file_get_contents($this->doubleCcTldFile);
                $this->doubleCcTlds = array_flip(preg_split('/[r\n]+/', $raw));
            } else if( substr($this->doubleCcTldFile, -4) === '.php' ) {
                // It's a php file, include it. Should already be flipped
                $this->doubleCcTlds = include $this->doubleCcTldFile;
            } else {
                $raw = file_get_contents($this->doubleCcTldFile);
                $this->doubleCcTlds = array_flip(preg_split('/[r\n]+/', $raw));
            }
        }
        
        return array_key_exists($fqdn, $this->doubleCcTlds);
    } // function

    /**
     * Get Hostname to ask for.
     *
     * Performs the following steps:
     *
     * (1) Extract the hostname from the given URI
     * (2) Check if the "hostname" is an ip
     * (3a) IS_IP Reverse the IP (1.2.3.4 -> 4.3.2.1)
     * (3b) IS_FQDN Check if is in "CC-2-level-TLD"
     * (3b1) IS_IN_2LEVEL: we want the last three names
     * (3b2) IS_NOT_2LEVEL: we want the last two names
     * (4) return the FQDN to query.
     *
     * @param string $uri       URL to check. 
     * @param string $blacklist Blacklist to check against. 
     *
     * @access protected
     * @return string Host to lookup
     */
    protected function getHostForLookup($uri, $blacklist) 
    {
        // (1) Extract the hostname from the given URI
        $host       = '';
        $parsed_uri = parse_url($uri);

        if (empty($parsed_uri['host'])) {
            return false;
        }

        $host       = urldecode($parsed_uri['host']);
        // (2) Check if the "hostname" is an ip
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            // (3a) IS_IP Reverse the IP (1.2.3.4 -> 4.3.2.1)
            $host = $this->reverseIp($host);
        } else {
            $host_elements = explode('.', $host);
            while (count($host_elements) > 3) {
                array_shift($host_elements);
            } // while
            $host_3_elements = implode('.', $host_elements);
            
            $host_elements = explode('.', $host);
            while (count($host_elements) > 2) {
                array_shift($host_elements);
            } // while
            $host_2_elements = implode('.', $host_elements);
            
            // (3b) IS_FQDN Check if is in "CC-2-level-TLD"
            if ($this->isDoubleCcTld($host_2_elements)) {
                // (3b1) IS_IN_2LEVEL: we want the last three names
                $host = $host_3_elements;
            } else {
                // (3b2) IS_NOT_2LEVEL: we want the last two names
                $host = $host_2_elements;
            } // if
        } // if
        // (4) return the FQDN to query
        $host .= '.'.$blacklist;
        return $host;
    } // function
    
} // class
