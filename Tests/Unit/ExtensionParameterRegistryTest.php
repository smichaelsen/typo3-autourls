<?php
namespace Smichaelsen\Autourls\Tests\Unit;

use Smichaelsen\Autourls\ExtensionParameterRegistry;
use TYPO3\CMS\Core\Tests\UnitTestCase;

class ExtensionParameterRegistryTest extends UnitTestCase
{

    /**
     *
     */
    public function setUp()
    {
        ExtensionParameterRegistry::reset();
    }

    /**
     * @test
     */
    public function resetWorks()
    {
        ExtensionParameterRegistry::register('test', 'querystring');
        ExtensionParameterRegistry::reset();
        self::assertSame(0, count(ExtensionParameterRegistry::get()));
    }

    /**
     * @test
     */
    public function addedRoutesAreReturned()
    {
        $routes = [
            'test1' => [
                'queryString' => 'test1querystring',
                'tableName' => 'test1tablename',
            ],
            'test2' => [
                'queryString' => 'test2querystring',
                'tableName' => 'test2tablename',
            ],
        ];
        foreach ($routes as $routeName => $routeConfig) {
            ExtensionParameterRegistry::register($routeName, $routeConfig['queryString'], $routeConfig['tableName']);
        }
        self::assertEquals($routes, ExtensionParameterRegistry::get());
    }
}
