<?php

class NetDNSBLTest extends PHPUnit_Framework_TestCase
{
    private $_rbl;
    
    /**
     * Set up Testcase for Net_DNSBL
     *
     * @return boolean true on success, false on failure
     */
    protected function setUp()
    {
        $dpf = __DIR__ . '/dnsbl.cache.json';
        $this->_rbl = new \DNSBL\DNSBL(array(
            'blacklists' => array(
                'sbl-xbl.spamhaus.org',
                'bl.spamcop.net'
            ),
            'dumpFile' => $dpf,
            'preloadFile' => $dpf,
        ));
    }
    
    /**
     * Test if known spam hosts are always identified correctly as such.
     *
     * @return boolean true on success, false on failure
     */
    public function testHostsAlwaysAreListed()
    {
        $this->assertTrue($this->_rbl->isListed("127.0.0.2"));
    }

    /**
     * Test if hosts that should not be know as spam hostsare always
     * identified correctly as such.
     *
     * @return boolean true on success, false on failure
     */
    public function testTrustworthyHostsArentListed()
    {
        $this->_rbl->setBlacklists(array('sbl.spamhaus.org'));
        $this->assertFalse($this->_rbl->isListed("mail.nohn.net"));
        $this->assertFalse($this->_rbl->isListed("212.112.226.205"));
        $this->assertFalse($this->_rbl->isListed("smtp1.google.com"));
    }

    /**
     * Test public setters
     *
     * @return boolean true on success, false on failure
     */
    public function testSetters()
    {
        $this->assertTrue($this->_rbl->setBlacklists(array('sbl.spamhaus.org')));
        $this->assertEquals(array('sbl.spamhaus.org'), $this->_rbl->getBlacklists());
        $this->assertFalse($this->_rbl->setBlacklists('dnsbl.sorbs.net'));
    }

    /**
     * Test public setters and include some lookups.
     *
     * @return boolean true on success, false on failure
     */
    public function testSettersAndLookups()
    {
        $this->_rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertEquals(array('dnsbl.sorbs.net'), $this->_rbl->getBlacklists());
        $this->assertFalse($this->_rbl->isListed("mail.nohn.net"));
        $this->assertTrue($this->_rbl->isListed("88.77.163.166"));
    }

    /**
     * Test getDetails()
     *
     * @return boolean true on success, false on failure
     */
    public function testGetDetails()
    {
        $this->_rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertTrue($this->_rbl->isListed("88.77.163.166"));
        
        $r = $this->_rbl->getDetails("88.77.163.166");
        $this->assertEquals(array(
            "dnsbl.sorbs.net" => array(
                array(
                    "host" => "166.163.77.88.dnsbl.sorbs.net",
                    "class" => "IN",
                    "ttl" => 2975,
                    "type" => "A",
                    "ip" => "127.0.0.10"
                ), array(
                    "host" => "166.163.77.88.dnsbl.sorbs.net",
                    "class" => "IN",
                    "ttl" => 2975,
                    "type" => "TXT",
                    "txt" => "Dynamic IP Addresses See: http://www.sorbs.net/lookup.shtml?88.77.163.166",
                    "entries" => array(
                        "Dynamic IP Addresses See: http://www.sorbs.net/lookup.shtml?88.77.163.166"
                    )
                )
            )
        ), $r);
    }

    /**
     * Test getListingBlacklists()
     *
     * @return boolean true on success, false on failure
     */
    public function testGetListingBlacklists()
    {
        $this->_rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertTrue($this->_rbl->isListed("88.77.163.166"));
        
        $r = $this->_rbl->getListingBlacklists("88.77.163.166");
        $this->assertEquals(array("dnsbl.sorbs.net"), $r);
        
        $r2 = $this->_rbl->getListingBlacklists("www.google.de");
        $this->assertEquals(array(), $r2);
    }
    
    /**
     * Test results with multiple blacklists (host not listed)
     *
     * @return boolean true on success, false on failure
     */
    public function testMultipleBlacklists()
    {
        $this->_rbl->setBlackLists(array(
            'sbl-xbl.spamhaus.org',
            'bl.spamcop.net'
        ));
        
        $this->assertFalse($this->_rbl->isListed('212.112.226.205'));
        
        $r = $this->_rbl->getListingBlacklists('212.112.226.205');
        $this->assertEquals(array(), $r);
    }

    /**
     * Test results with multiple blacklists (listed test host)
     *
     * @return boolean true on success, false on failure
     */
    public function testIsListedMulti()
    {
        $this->_rbl->setBlackLists(array(
            'sbl-xbl.spamhaus.org',
            'bl.spamcop.net'
        ));
        $this->assertTrue($this->_rbl->isListed('127.0.0.2', true));
    }

    /**
     * Test getBlacklists() with multiple blacklists (listed test host)
     *
     * @return boolean true on success, false on failure
     */
    public function testGetListingBlacklistsMulti()
    {
        $this->_rbl->setBlackLists(array(
            'xbl.spamhaus.org',
            'sbl.spamhaus.org',
            'bl.spamcop.net'
        ));
        
        $this->assertTrue($this->_rbl->isListed('127.0.0.2', true));
        $this->assertEquals(
            array(
                'sbl.spamhaus.org',
                'bl.spamcop.net'
            ),
            $this->_rbl->getListingBlacklists('127.0.0.2')
        );
        
        $this->assertFalse($this->_rbl->isListed('smtp1.google.com', true));
        $this->assertEquals(array(), $this->_rbl->getListingBlacklists('smtp1.google.com'));
        $this->assertEquals(array(
            'xbl.spamhaus.org' => array(),
            'sbl.spamhaus.org' => array(),
            'bl.spamcop.net' => array()
        ), $this->_rbl->getDetails('smtp1.google.com'));
    }

    /**
     * Test Bokus
     *
     * @return boolean true on success, false on failure
     */
    public function testBogusInput()
    {
        $this->_rbl->setBlacklists(array('rbl.efnet.org'));
        $this->assertFalse($this->_rbl->isListed(null));
        $this->assertNull($this->_rbl->getDetails(null));
        $this->assertFalse($this->_rbl->isListed(false));
        $this->assertNull($this->_rbl->getDetails(false));
        $this->assertFalse($this->_rbl->isListed(true));
        $this->assertNull($this->_rbl->getDetails(true));
    }

    /**
     * Test getListingBl() does not break silently if isListed() was
     * called with 2nd paramter
     *
     * @see http://pear.php.net/bugs/bug.php?id=16382
     *
     * @return boolean true on success, false on failure
     */
    public function testGetListingBlacklistsDoesNotBreakSilentlyIfHostIsListed()
    {
        $this->_rbl->setBlacklists(array('bl.spamcop.net','b.barracudacentral.org'));
        $ip = '127.0.0.2';
        $this->assertTrue($this->_rbl->isListed($ip, true));
        $this->assertEquals(
            array('bl.spamcop.net', 'b.barracudacentral.org'), 
            $this->_rbl->getListingBlacklists($ip)
        );
    }

    /**
     * Test getListingBl() does not break silently if isListed() was
     * called with 2nd paramter
     *
     * @see http://pear.php.net/bugs/bug.php?id=16382
     *
     * @return boolean true on success, false on failure
     */
    public function testGetListingBlDoesNotBreakSilentlyIfHostIsNotListed()
    {
        $this->_rbl->setBlacklists(array('bl.spamcop.net','b.barracudacentral.org'));
        $ip = '127.0.0.1';
        $this->assertFalse($this->_rbl->isListed($ip, true));
        $this->assertEquals(array(), $this->_rbl->getListingBlacklists($ip));
        $this->assertFalse($this->_rbl->isListed($ip));
        $this->assertEquals(array(), $this->_rbl->getListingBlacklists($ip));
    }
}
