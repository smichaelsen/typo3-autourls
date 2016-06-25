<?php
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\SingletonInterface;

class UrlDecodingService extends AbstractUrlMapService implements SingletonInterface
{

    /**
     * @param $pagePath
     * @return array
     */
    public function decodeFromPagePath($pagePath)
    {
        $queryString = $this->findQueryStringForPathInMap($pagePath);
        if ($queryString === null) {
            return null;
        }
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
            ->select('querystring')
            ->from('tx_autourls_map')
            ->where(
                $queryBuilder->expr()->eq('path_hash', $this->fastHash($path)),
                $queryBuilder->expr()->eq('is_shortcut', 0)
            )
            ->execute()->fetchColumn();
        return $value !== false ? (string)$value : null;
    }

}
