<?php
namespace Smichaelsen\Autourls\Tests\Unit\Service;

use Smichaelsen\Autourls\Service\UrlDecodingService;
use TYPO3\CMS\Core\Database\DatabaseConnection;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class UrlDecodingServiceTest extends UnitTestCase
{

    /**
     * @var UrlDecodingService
     */
    protected $subject;

    public function setUp()
    {
        $this->subject = new UrlDecodingService();
    }

    /**
     * @test
     * @dataProvider getPagePathMappingData
     * @param string $requestedPath
     * @param array $expectedParameterArray
     */
    public function decodeFromPagePath($requestedPath, $expectedParameterArray)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|DatabaseConnection $databaseConnectionMock */
        $databaseConnectionMock = $this->createMock(DatabaseConnection::class);

        $databaseConnectionMock
            ->expects($this->atLeastOnce())
            ->method('exec_SELECTgetSingleRow')
            ->willReturn($this->getUrlMapRecord($requestedPath));
        $this->subject->setDatabaseConnection($databaseConnectionMock);
        self::assertEquals($expectedParameterArray, $this->subject->decodeFromPagePath($requestedPath));
    }

    /**
     * @param $path
     * @return array|null
     */
    protected function getUrlMapRecord($path)
    {
        $urlMap = [
            [
                'querystring' => 'id=1',
                'chash' => '',
                'path' => '',
            ],
            [
                'querystring' => 'id=6',
                'chash' => '',
                'path' => 'my/page',
            ],
            [
                'querystring' => 'id=56&tx_myext[product]=6',
                'chash' => '66ff49e23ec9f530cb0e30a4e53c70af',
                'path' => 'my/detail/page/testproduct',
            ],
            [
                'querystring' => 'id=56',
                'chash' => '',
                'path' => 'my/detail/page',
            ],
        ];
        if (strpos($path, '?') !== false) {
            $path = substr($path, 0, strpos($path, '?'));
        }
        $path = trim($path, '/');
        foreach ($urlMap as $urlMapRecord) {
            if ($urlMapRecord['path'] === $path) {
                return $urlMapRecord;
            }
        }
        return null;
    }

    /**
     * @return array
     */
    public function getPagePathMappingData()
    {
        return [
            [
                '', // requested path
                ['id' => '1'], // expected parameter array
            ],
            [
                '/',
                ['id' => '1'],
            ],
            [
                '?foo=bar',
                ['id' => '1', 'foo' => 'bar'],
            ],
            [
                'my/page',
                ['id' => '6'],
            ],
            [
                'my/page?foo=bar',
                ['id' => '6', 'foo' => 'bar'],
            ],
            [
                'my/page/?foo=bar',
                ['id' => '6', 'foo' => 'bar'],
            ],
            [
                'my/detail/page/testproduct',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af'],
            ],
            [
                'my/detail/page/testproduct?foo=bar',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af', 'foo' => 'bar'],
            ],
            [
                'my/detail/page/?tx_myext[product]=6&cHash=66ff49e23ec9f530cb0e30a4e53c70af',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af'],
            ],
            [
                'my/detail/page/?tx_myext[product]=6&cHash=66ff49e23ec9f530cb0e30a4e53c70af&foo=bar',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af', 'foo' => 'bar'],
            ],
            [
                'non/existing/page',
                null,
            ],
        ];
    }

}
