<?php

class SURBLTest extends \PHPUnit\Framework\TestCase
{
    private $_surbl;

    /**
     * Set up Testcase for SURBL
     *
     * @return boolean true on success, false on failure
     */
    protected function setUp()
    {
        $dpf = __DIR__ . '/surbl.cache.json';
        $this->_surbl = new \DNSBL\SURBL(array(
            'blacklists' => array(
                'multi.surbl.org'
            ),
            'dumpFile' => $dpf,
            'preloadFile' => $dpf,
        ));
    }
    
    /**
     * Tests if a test spam URL is always correctly identified as such. 
     *
     * @return boolean true on success, false on failure
     */
    public function testSpamUrlsAlwaysGetReportedAsSpam()
    {
        $this->assertTrue(
            $this->_surbl->isListed(
                'http://surbl-org-permanent-test-point.com/justatest'
            )
        );
        $this->assertTrue(
            $this->_surbl->isListed(
                'http://wasdavor.surbl-org-permanent-test-point.com/justatest'
            )
        );
        $this->assertTrue($this->_surbl->isListed('http://127.0.0.2/'));
        $this->assertTrue($this->_surbl->isListed('http://127.0.0.2/justatest'));
    }

    /**
     * Tests if an URL that should not be spam is always correctly identified as 
     * such. 
     *
     * @return boolean true on success, false on failure
     */
    public function testNoSpamUrlsNeverGetReportedAsSpam()
    {
        $this->assertFalse($this->_surbl->isListed('http://www.nohn.net'));
        $this->assertFalse($this->_surbl->isListed('http://www.php.net/'));
        $this->assertFalse($this->_surbl->isListed('http://www.heise.de/24234234?url=lala'));
        $this->assertFalse($this->_surbl->isListed('http://www.nohn.net/blog/'));
        $this->assertFalse($this->_surbl->isListed('http://213.147.6.150/atest'));
        $this->assertFalse(
            $this->_surbl->isListed(
                'http://www.google.co.uk/search'.
                '?hl=en&q=test&btnG=Google+Search&meta='
            )
        );
    }

    /**
     * Tests if a set of spam and no-spam URLs is always correctly identified as 
     * such. 
     *
     * @return boolean true on success, false on failure
     */
    public function testMixedSpamAndNospamUrlsWorkAsExpected()
    {
        $this->assertFalse($this->_surbl->isListed('http://www.nohn.net'));
        $this->assertTrue(
            $this->_surbl->isListed('http://surbl-org-permanent-test-point.com')
        );
        $this->assertTrue($this->_surbl->isListed('http://127.0.0.2/justatest'));
        $this->assertFalse($this->_surbl->isListed('http://213.147.6.150/atest'));
        $this->assertFalse($this->_surbl->isListed('http://www.php.net'));
        $this->assertFalse($this->_surbl->isListed('http://www.google.com'));
        $this->assertFalse(
            $this->_surbl->isListed(
                'http://www.google.co.uk/search?hl=en&q=test&btnG=Google+Search&meta='
            )
        );
    }

    /**
     * Tests if invalid arguments always return false.
     *
     * @return boolean true on success, false on failure
     */
    public function testInvalidArguments()
    {
        $this->assertFalse($this->_surbl->isListed('hurgahurga'));
        $this->assertFalse($this->_surbl->isListed(null));
        $this->assertFalse($this->_surbl->isListed(false));
        $this->assertFalse($this->_surbl->isListed(true));
    }
}
