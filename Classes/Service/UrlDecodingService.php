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
        $questionMarkStrPos = strpos($path, '?');
        if ($questionMarkStrPos !== false) {
            $remainingParameters = substr($path, $questionMarkStrPos + 1);
            $path = substr($path, 0, $questionMarkStrPos);
        }
        $record = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            'querystring, chash',
            'tx_autourls_map JOIN sys_domain ON (tx_autourls_map.rootpage_id = sys_domain.pid) ',
            'tx_autourls_map.path = ' . $this->getDatabaseConnection()->fullQuoteStr(rtrim($path, '/'), 'tx_autourls_map') . ' AND tx_autourls_map.is_shortcut = 0 AND sys_domain.hidden = 0 AND sys_domain.domainName = ' . $this->getDatabaseConnection()->fullQuoteStr(GeneralUtility::getIndpEnv('HTTP_HOST'), 'sys_domain')
        );
        if (is_array($record) && !empty($record['querystring'])) {
            $querystring = $record['querystring'];
            if (!empty($record['chash'])) {
                $querystring .= '&cHash=' . $record['chash'];
            }
            if (!empty($remainingParameters)) {
                $querystring .= '&' . $remainingParameters;
            }
            return $querystring;
        }
        return null;
    }

}
