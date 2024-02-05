<?php

namespace DNSBL\Tests;

use DNSBL\SURBL;
use PHPUnit\Framework\TestCase;

/**
 * @psalm-suppress MissingDependency
 */
final class SURBLTest extends TestCase
{
    /** @var SURBL */
    private $surbl;

    protected function setUp(): void
    {
        $dpf = __DIR__ . '/surbl.cache.json';
        $this->surbl = new SURBL([
            'blacklists' => [
                'multi.surbl.org'
            ],
            'dumpFile' => $dpf,
            'preloadFile' => $dpf,
        ]);
    }

    /**
     * Tests if a test spam URL is always correctly identified as such.
     */
    public function testSpamUrlsAlwaysGetReportedAsSpam(): void
    {
        $this->assertTrue(
            $this->surbl->isListed(
                'http://surbl-org-permanent-test-point.com/justatest'
            )
        );
        $this->assertTrue(
            $this->surbl->isListed(
                'http://wasdavor.surbl-org-permanent-test-point.com/justatest'
            )
        );
        $this->assertTrue($this->surbl->isListed('http://127.0.0.2/'));
        $this->assertTrue($this->surbl->isListed('http://127.0.0.2/justatest'));
    }

    /**
     * Tests if a URL that should not be spam is always correctly identified as
     * such.
     */
    public function testNoSpamUrlsNeverGetReportedAsSpam(): void
    {
        $this->assertFalse($this->surbl->isListed('http://www.nohn.net'));
        $this->assertFalse($this->surbl->isListed('http://www.php.net/'));
        $this->assertFalse($this->surbl->isListed('http://www.heise.de/24234234?url=lala'));
        $this->assertFalse($this->surbl->isListed('http://www.nohn.net/blog/'));
        $this->assertFalse($this->surbl->isListed('http://213.147.6.150/atest'));
        $this->assertFalse(
            $this->surbl->isListed(
                'http://www.google.co.uk/search' .
                '?hl=en&q=test&btnG=Google+Search&meta='
            )
        );
    }

    /**
     * Tests if a set of spam and no-spam URLs is always correctly identified as
     * such.
     */
    public function testMixedSpamAndNospamUrlsWorkAsExpected(): void
    {
        $this->assertFalse($this->surbl->isListed('http://www.nohn.net'));
        $this->assertTrue(
            $this->surbl->isListed('http://surbl-org-permanent-test-point.com')
        );
        $this->assertTrue($this->surbl->isListed('http://127.0.0.2/justatest'));
        $this->assertFalse($this->surbl->isListed('http://213.147.6.150/atest'));
        $this->assertFalse($this->surbl->isListed('http://www.php.net'));
        $this->assertFalse($this->surbl->isListed('http://www.google.com'));
        $this->assertFalse(
            $this->surbl->isListed(
                'http://www.google.co.uk/search?hl=en&q=test&btnG=Google+Search&meta='
            )
        );
    }

    /**
     * Tests if invalid arguments always return false.
     */
    public function testInvalidArguments(): void
    {
        $this->assertFalse($this->surbl->isListed('hurgahurga'));
    }
}
