<?php

namespace DNSBL\Tests;

use DNSBL\DNSBL;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress MissingDependency
 */
final class DNSBLTest extends TestCase
{
    /** @var DNSBL */
    private $rbl;

    /**
     * Set up Testcase for Net_DNSBL
     */
    protected function setUp(): void
    {
        $dpf = __DIR__ . '/dnsbl.cache.json';
        $this->rbl = new DNSBL(array(
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
     */
    public function testHostsAlwaysAreListed(): void
    {
        $this->assertTrue($this->rbl->isListed("127.0.0.2"));
    }

    /**
     * Test if hosts that should not be known as spam hostsare always
     * identified correctly as such.
     */
    public function testTrustworthyHostsArentListed(): void
    {
        $this->rbl->setBlacklists(array('sbl.spamhaus.org'));
        $this->assertFalse($this->rbl->isListed("mail.nohn.net"));
        $this->assertFalse($this->rbl->isListed("212.112.226.205"));
        $this->assertFalse($this->rbl->isListed("smtp1.google.com"));
    }

    /**
     * Test public setters
     */
    public function testSetters(): void
    {
        $this->assertTrue($this->rbl->setBlacklists(array('sbl.spamhaus.org')));
        $this->assertEquals(array('sbl.spamhaus.org'), $this->rbl->getBlacklists());
        //$this->assertTrue($this->_rbl->setBlacklists('dnsbl.sorbs.net'));
    }

    /**
     * Test public setters and include some lookups.
     */
    public function testSettersAndLookups(): void
    {
        $this->rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertEquals(array('dnsbl.sorbs.net'), $this->rbl->getBlacklists());
        $this->assertFalse($this->rbl->isListed("mail.nohn.net"));
        $this->assertTrue($this->rbl->isListed("88.77.163.166"));
    }

    /**
     * Test getDetails()
     */
    public function testGetDetails(): void
    {
        $this->rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertTrue($this->rbl->isListed("88.77.163.166"));

        $r = $this->rbl->getDetails("88.77.163.166");
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
     */
    public function testGetListingBlacklists(): void
    {
        $this->rbl->setBlacklists(array('dnsbl.sorbs.net'));
        $this->assertTrue($this->rbl->isListed("88.77.163.166"));

        $r = $this->rbl->getListingBlacklists("88.77.163.166");
        $this->assertEquals(array("dnsbl.sorbs.net"), $r);

        $r2 = $this->rbl->getListingBlacklists("www.google.de");
        $this->assertEquals(array(), $r2);
    }

    /**
     * Test results with multiple blacklists (host not listed)
     */
    public function testMultipleBlacklists(): void
    {
        $this->rbl->setBlacklists(array(
            'sbl-xbl.spamhaus.org',
            'bl.spamcop.net'
        ));

        $this->assertFalse($this->rbl->isListed('212.112.226.205'));

        $r = $this->rbl->getListingBlacklists('212.112.226.205');
        $this->assertEquals(array(), $r);
    }

    /**
     * Test results with multiple blacklists (listed test host)
     */
    public function testIsListedMulti(): void
    {
        $this->rbl->setBlacklists(array(
            'sbl-xbl.spamhaus.org',
            'bl.spamcop.net'
        ));
        $this->assertTrue($this->rbl->isListed('127.0.0.2', true));
    }

    /**
     * Test getBlacklists() with multiple blacklists (listed test host)
     */
    public function testGetListingBlacklistsMulti(): void
    {
        $this->rbl->setBlacklists(array(
            'xbl.spamhaus.org',
            'sbl.spamhaus.org',
            'bl.spamcop.net'
        ));

        $this->assertTrue($this->rbl->isListed('127.0.0.2', true));
        $this->assertEquals(
            array(
                'sbl.spamhaus.org',
                'bl.spamcop.net'
            ),
            $this->rbl->getListingBlacklists('127.0.0.2')
        );

        $this->assertFalse($this->rbl->isListed('smtp1.google.com', true));
        $this->assertEquals(array(), $this->rbl->getListingBlacklists('smtp1.google.com'));
        $this->assertEquals(array(
            'xbl.spamhaus.org' => array(),
            'sbl.spamhaus.org' => array(),
            'bl.spamcop.net' => array()
        ), $this->rbl->getDetails('smtp1.google.com'));
    }

    /**
     * Test getListingBl() does not break silently if isListed() was
     * called with 2nd paramter
     *
     * @see http://pear.php.net/bugs/bug.php?id=16382
     */
    public function testGetListingBlacklistsDoesNotBreakSilentlyIfHostIsListed(): void
    {
        $this->rbl->setBlacklists(array('bl.spamcop.net','b.barracudacentral.org'));
        $ip = '127.0.0.2';
        $this->assertTrue($this->rbl->isListed($ip, true));
        $this->assertEquals(
            array('bl.spamcop.net', 'b.barracudacentral.org'),
            $this->rbl->getListingBlacklists($ip)
        );
    }

    /**
     * Test getListingBl() does not break silently if isListed() was
     * called with 2nd paramter
     *
     * @see http://pear.php.net/bugs/bug.php?id=16382
     */
    public function testGetListingBlDoesNotBreakSilentlyIfHostIsNotListed(): void
    {
        $this->rbl->setBlacklists(array('bl.spamcop.net','b.barracudacentral.org'));
        $ip = '127.0.0.1';
        $this->assertFalse($this->rbl->isListed($ip, true));
        $this->assertEquals(array(), $this->rbl->getListingBlacklists($ip));
        $this->assertFalse($this->rbl->isListed($ip));
        $this->assertEquals(array(), $this->rbl->getListingBlacklists($ip));
    }

    public function testReverseIp(): void
    {
        // IPv4:
        $this->assertEquals('1.0.0.127', $this->rbl->reverseIp('127.0.0.1'));
        $this->assertEquals('1.0.0.127', $this->rbl->reverseIp('[127.0.0.1]'));

        // IPv6:
        $this->assertEquals(
            'b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2',
            $this->rbl->reverseIp('2001:db8::567:89ab')
        );
        $this->assertEquals(
            'b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2',
            $this->rbl->reverseIp('[2001:db8::567:89ab]')
        );

        // Invalid IP address:
        $this->expectException(\Exception::class);
        $this->rbl->reverseIp('foobar');
    }
}
