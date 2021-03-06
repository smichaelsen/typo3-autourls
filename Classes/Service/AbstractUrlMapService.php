<?php
declare(strict_types=1);
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

abstract class AbstractUrlMapService
{

    /**
     * Uses the very fast crc32 hash.
     * This is NO cryptographic hash function(!!) but hashes strings quickly down
     * to integers for better database performance.
     *
     * @param string $string
     * @return int
     */
    protected function fastHash(string $string):int
    {
        return (int)sprintf('%u', crc32($string));
    }

    /**
     * @param string $queryString
     * @return array
     */
    protected function queryStringToParametersArray(string $queryString):array
    {
        parse_str($queryString, $urlParameters);
        return $urlParameters;
    }

    /**
     * @param array $urlParameters
     * @return string
     */
    protected function parametersArrayToQueryString(array $urlParameters):string
    {
        return http_build_query($urlParameters);
    }

    /**
     * @return QueryBuilder
     */
    protected function getMapQueryBuilder():QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_autourls_map');
    }

}
