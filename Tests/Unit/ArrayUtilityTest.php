<?php
namespace Smichaelsen\Autourls\Tests\Unit;

use Smichaelsen\Autourls\ArrayUtility;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class ArrayUtilityTest extends UnitTestCase
{

    /**
     * @test
     * @dataProvider arrayDataProvider
     * @param array $needle
     * @param array $haystack
     * @param bool $expected
     */
    public function arrayHasAllKeysOfArray($needle, $haystack, $expected)
    {
        $this->assertSame($expected, ArrayUtility::array_has_all_keys_of_array($needle, $haystack));
    }

    /**
     * @return array
     */
    public function arrayDataProvider()
    {
        return [
            [
                [],
                [],
                true,
            ],
            [
                ['foo' => 'bar'],
                [],
                true,
            ],
            [
                [],
                ['foo' => 'bar'],
                false,
            ],
            [
                ['foo' => 'bar'],
                ['foo' => 'bar'],
                true,
            ],
            [
                ['foo' => 'bar', 'fooo' => 'bar'],
                ['foo' => 'bar'],
                true,
            ],
            [
                ['foo' => 'bar'],
                ['foo' => 'bar', 'fooo' => 'bar'],
                false,
            ],
            [
                ['foo' => ['foo' => 'bar', 'fooo' => 'bar']],
                ['foo' => ['foo' => 'bar']],
                true,
            ],
            [
                ['foo' => ['foo' => 'bar']],
                ['foo' => ['foo' => 'bar', 'fooo' => 'bar']],
                false,
            ],
        ];
    }
}
