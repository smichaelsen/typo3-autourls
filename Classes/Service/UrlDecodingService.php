<?php
declare(strict_types=1);
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\SingletonInterface;

class UrlDecodingService extends AbstractUrlMapService implements SingletonInterface
{

    /**
     * @param string $pagePath
     * @return array|null
     */
    public function decodeFromPagePath(string $pagePath)
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
    private function findQueryStringForPathInMap(string $path)
    {
        $queryBuilder = $this->getMapQueryBuilder();
        $value = $queryBuilder
            ->select('tx_autourls_map.querystring')
            ->from('tx_autourls_map')
            ->join(
                'tx_autourls_map',
                'sys_domain',
                'sys_domain',
                $queryBuilder->expr()->eq('tx_autourls_map.rootpage_id', 'sys_domain.pid')
            )
            ->where(
                $queryBuilder->expr()->eq('tx_autourls_map.path', $queryBuilder->createNamedParameter(rtrim($path, '/'))),
                $queryBuilder->expr()->eq('tx_autourls_map.is_shortcut', 0)
            )
            ->execute()->fetchColumn();
        return $value !== false ? (string)$value : null;
    }

}
