<?php
namespace Smichaelsen\Autourls\Tests\Unit\Service\ParameterEncoder;

use Smichaelsen\Autourls\Service\ParameterEncoder\LanguageEncoder;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class LanguageEncoderTest extends UnitTestCase
{

    /**
     * @var LanguageEncoder
     */
    protected $subject;

    /**
     *
     */
    public function setUp()
    {
        $this->subject = new LanguageEncoder();
    }

    /**
     * @test
     */
    public function nothingIsAddedIfLanguageIsZero()
    {
        $originalUrlEncodingData = [
            'remainingUrlParameters' => [
                'foo' => 'bar',
                'L' => 0,
            ],
            'encodedPathSegments' => [],
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        self::assertFalse(isset($resultUrlEncodingData['remainingUrlParameters']['L']));
        self::assertSame(0, count($resultUrlEncodingData['encodedPathSegments']));
    }

    /**
     * @test
     */
    public function nothingIsAddedIfLanguageIsNotSet()
    {
        $originalUrlEncodingData = [
            'remainingUrlParameters' => [
                'foo' => 'bar',
            ],
            'encodedPathSegments' => [],
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        self::assertSame(0, count($resultUrlEncodingData['encodedPathSegments']));
    }

    /**
     * @test
     */
    public function readIsoCodeFromLanguageMap()
    {
        $languageMap = [
            '2' => ['language_isocode' => 'fi'],
            '5' => ['language_isocode' => 'de'],
            '3' => ['language_isocode' => 'es'],
            '11' => ['language_isocode' => 'it'],
        ];
        $this->inject($this->subject, 'languageMap', $languageMap);
        $originalUrlEncodingData = [
            'remainingUrlParameters' => [
                'foo' => 'bar',
                'L' => '5'
            ],
            'encodedPathSegments' => [],
        ];
        $resultUrlEncodingData = $this->subject->encode($originalUrlEncodingData);
        self::assertSame('de', $resultUrlEncodingData['encodedPathSegments'][0]);
        self::assertSame(1, count($resultUrlEncodingData['encodedPathSegments']));
        self::assertSame(5, $resultUrlEncodingData['targetLanguageUid']);
    }

}
