<?php
namespace Smichaelsen\Autourls\Tests\Unit\Service\ParameterEncoder;

use Smichaelsen\Autourls\Service\ParameterEncoder\CHashEncoder;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class CHashEncoderTest extends UnitTestCase
{

    /**
     * @var CHashEncoder
     */
    protected $subject;

    /**
     *
     */
    public function setUp()
    {
        $this->subject = new CHashEncoder();
    }

    /**
     * @test
     */
    public function dontSetCHashIfAllParametersWereEncoded()
    {
        $originalUrlEncodingData = [
            'remainingUrlParameters' => [],
            'cHash' => null,
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        $this->assertNull($resultUrlEncodingData['cHash']);
    }

    /**
     * @test
     */
    public function dontSetCHashIfNoCHashParameterIsGiven()
    {
        $originalUrlEncodingData = [
            'remainingUrlParameters' => ['foo' => 'bar'],
            'cHash' => null,
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        $this->assertNull($resultUrlEncodingData['cHash']);
    }

    /**
     * @test
     */
    public function dontSetCHashIfMoreParametersAreLeft()
    {
        $originalUrlEncodingData = [
            'remainingUrlParameters' => ['foo' => 'bar', 'cHash' => 'abcdefghijkl'],
            'cHash' => null,
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        $this->assertNull($resultUrlEncodingData['cHash']);
    }

    /**
     * @test
     */
    public function setCHashIfOnlyCHashParameterIsLeft()
    {
        $cHash = 'abcdefghijkl';
        $originalUrlEncodingData = [
            'remainingUrlParameters' => ['cHash' => $cHash],
            'cHash' => null,
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        $this->assertSame($cHash, $resultUrlEncodingData['cHash']);
    }
}
