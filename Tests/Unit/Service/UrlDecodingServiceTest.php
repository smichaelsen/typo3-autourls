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
     * @param string $message
     */
    public function decodeFromPagePath($requestedPath, $expectedParameterArray, $message)
    {
        /** @var \PHPUnit_Framework_MockObject_MockObject|DatabaseConnection $databaseConnectionMock */
        $databaseConnectionMock = $this->createMock(DatabaseConnection::class);

        $databaseConnectionMock
            ->expects($this->atLeastOnce())
            ->method('exec_SELECTgetSingleRow')
            ->willReturn($this->getUrlMapRecord($requestedPath));
        $this->subject->setDatabaseConnection($databaseConnectionMock);
        self::assertEquals($expectedParameterArray, $this->subject->decodeFromPagePath($requestedPath), $message);
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
                'mapped page with empty path' // message
            ],
            [
                '/',
                ['id' => '1'],
                'mapped page with empty path called with just a slash'
            ],
            [
                '?foo=bar',
                ['id' => '1', 'foo' => 'bar'],
                'GET params appended to page with empty path'
            ],
            [
                'my/page',
                ['id' => '6'],
                'mapped page by path'
            ],
            [
                'my/page?foo=bar',
                ['id' => '6', 'foo' => 'bar'],
                'GET params appenden to mapped page by path'
            ],
            [
                'my/page/?foo=bar',
                ['id' => '6', 'foo' => 'bar'],
                'GET params appenden to mapped page by path called with trailing slash'
            ],
            [
                'my/detail/page/testproduct',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af'],
                'page path and extension parameters with mapped cHash'
            ],
            [
                'my/detail/page/testproduct?foo=bar',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af'],
                'page path and extension parameters with mapped cHash and additional GET params (have to be truncated internally to ensure correct cHash handling!)'
            ],
            [
                'my/detail/page/?tx_myext[product]=6&cHash=66ff49e23ec9f530cb0e30a4e53c70af',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af'],
                'page path and GET params including a cHash'
            ],
            [
                'my/detail/page/?tx_myext[product]=6&cHash=66ff49e23ec9f530cb0e30a4e53c70af&foo=bar',
                ['id' => '56', 'tx_myext' => ['product' => '6'], 'cHash' => '66ff49e23ec9f530cb0e30a4e53c70af', 'foo' => 'bar'],
                'page path and GET params including a cHash'
            ],
            [
                'non/existing/page',
                null,
                'call non existing page path'
            ],
            [
                '?id=12',
                ['id' => '12'],
                'call page by GET param'
            ],
        ];
    }

}
