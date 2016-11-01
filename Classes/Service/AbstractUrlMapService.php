<?php
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\Database\DatabaseConnection;

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
    protected function fastHash($string)
    {
        return (int)sprintf('%u', crc32($string));
    }

    /**
     * @param string $queryString
     * @return array
     */
    protected function queryStringToParametersArray($queryString)
    {
        parse_str($queryString, $urlParameters);
        return $urlParameters;
    }

    /**
     * @param array $urlParameters
     * @return string
     */
    protected function parametersArrayToQueryString(array $urlParameters)
    {
        return http_build_query($urlParameters);
    }

    /**
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }

}
