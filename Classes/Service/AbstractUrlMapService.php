<?php
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
        $urlParameters = [];
        foreach (explode('&', $queryString) as $parameter) {
            list($parameterName, $parameterValue) = explode('=', $parameter);
            $urlParameters[$parameterName] = $parameterValue;
        }
        return $urlParameters;
    }

    /**
     * @param array $urlParameters
     * @return string
     */
    protected function parametersArrayToQueryString(array $urlParameters):string
    {
        $queryStringParts = [];
        foreach ($urlParameters as $parameterName => $parameterValue) {
            $queryStringParts[] = $parameterName . '=' . $parameterValue;
        }
        return join('&', $queryStringParts);
    }

    /**
     * @return QueryBuilder
     */
    protected function getMapQueryBuilder():QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_autourls_map');
    }

}
