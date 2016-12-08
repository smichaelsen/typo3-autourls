<?php
namespace Smichaelsen\Autourls\Tests\Unit\Service;

use Smichaelsen\Autourls\Service\Slugifier;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class SlugifierTest extends UnitTestCase
{

    /**
     * @var Slugifier
     */
    protected $subject;

    /**
     *
     */
    public function setUp()
    {
        $this->subject = new Slugifier();
        $this->subject->injectCharsetConverter(new CharsetConverter());
    }

    /**
     * @test
     * @dataProvider slugifyData
     * @param string $source
     * @param string $expected
     */
    public function slugify($source, $expected)
    {
        $this->assertSame($expected, $this->subject->slugify($source));
    }

    /**
     * @return array
     */
    public function slugifyData()
    {
        return [
            ['', ''],
            ['Maya Mate', 'maya-mate'],
            ['Fish & Chips', 'fish-chips'],
            ['Fish \'n Chips', 'fish-n-chips'],
            ['Immer 100%?', 'immer-100'],
            ['$500? For a phone!?', '$500-for-a-phone'],
            ['//yeah\\', 'yeah'],
            ['Milchmädchenrechnung', 'milchmaedchenrechnung'],
            ['Россия', 'rossija'],
        ];
    }
}
