<?php
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class UrlDecodingService extends AbstractUrlMapService implements SingletonInterface
{

    /**
     * @param string $pagePath
     * @return array|null
     */
    public function decodeFromPagePath($pagePath)
    {
        $queryString = $this->findQueryStringForPathInMap($pagePath);
        if ($queryString === null) {
            return null;
        }
        $_SERVER['QUERY_STRING'] = $queryString;
        return $this->queryStringToParametersArray($queryString);
    }

    /**
     * @param string $path
     * @return string|null
     */
    private function findQueryStringForPathInMap($path)
    {
        $record = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            'querystring',
            'tx_autourls_map JOIN sys_domain ON (tx_autourls_map.rootpage_id = sys_domain.pid) ',
            'tx_autourls_map.path_hash = "' . $this->fastHash(rtrim($path, '/')) . '" AND tx_autourls_map.is_shortcut = 0 AND sys_domain.hidden = 0 AND sys_domain.domainName = ' . $this->getDatabaseConnection()->fullQuoteStr(GeneralUtility::getIndpEnv('HTTP_HOST'), 'sys_domain')
        );
        if (is_array($record) && !empty($record['querystring'])) {
            return $record['querystring'];
        }
        return null;
    }

}
