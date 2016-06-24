<?php
namespace Smichaelsen\Autourls\Hook;

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

class UrlRewritingHook
{

    /**
     * @param array $params
     */
    public function encodeUrl(array &$params)
    {
        $queryString = parse_url($params['LD']['totalURL'])['query'];
        $path = $this->findPathForQueryStringInMap($queryString);
        if ($path === null) {
            $urlParameters = $this->queryStringToParametersArray($queryString);
            $encodedPath = '';
            if (isset($urlParameters['id'])) {
                $encodedPath .= $this->getPathForPageId($urlParameters['id']);
                unset($urlParameters['id']);
            }
            $path = $encodedPath . $this->parametersArrayToQueryString($urlParameters);
            $this->insertOrRenewMapEntry($queryString, $path);
        }
        $prefix = $encodedPath = $this->getTemplateService()->setup['config.']['absRefPrefix'] ?? '/';
        $params['LD']['totalURL'] = $prefix . $path;
    }

    /**
     * @param array $params
     */
    public function decodeUrl(array $params)
    {
        /** @var TypoScriptFrontendController $typoscriptFrontendController */
        $typoscriptFrontendController = &$params['pObj'];
        $queryString = $this->findQueryStringForPathInMap($typoscriptFrontendController->siteScript);
        if ($queryString === null) {
            return;
        }
        $decodedUrlParameters = $this->queryStringToParametersArray($queryString);
        $typoscriptFrontendController->mergingWithGetVars($decodedUrlParameters);
        $typoscriptFrontendController->id = (int)$decodedUrlParameters['id'];
    }

    /**
     * Uses the very fast crc32 hash
     *
     * @param string $queryString
     * @return int
     */
    protected function hashQueryString(string $queryString):int
    {
        return (int)sprintf('%u', crc32($queryString));
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
     * @param int $id
     * @return string
     */
    protected function getPathForPageId(int $id):string
    {
        $pathSegments = [];
        $rootline = $this->getPageRepository()->getRootLine($id);
        foreach ($rootline as $rootlinePage) {
            if ($rootlinePage['is_siteroot']) {
                break;
            }
            $pathSegments[] = $this->slugify($rootlinePage['title']);
        }
        return join('/', array_reverse($pathSegments));
    }

    /**
     * @param string $title
     * @return string
     */
    protected function slugify(string $title):string
    {
        $charset = $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'] ?? 'utf-8';
        $slug = $this->getCharsetConverter()->conv_case($charset, $title, 'toLower');
        $slug = strip_tags($slug);
        $slug = preg_replace('/[ \-+_]+/', '-', $slug);
        $slug = $this->getCharsetConverter()->specCharsToASCII($charset, $slug);
        return $slug;
    }

    /**
     * @param string $queryString
     * @return string|null
     */
    protected function findPathForQueryStringInMap(string $queryString)
    {
        $queryBuilder = $this->getMapQueryBuilder();
        $value = $queryBuilder
            ->select('path')
            ->from('tx_autourls_map')
            ->where(
                $queryBuilder->expr()->eq('querystring_hash', $this->hashQueryString($queryString)),
                $queryBuilder->expr()->gt('encoding_expires', $GLOBALS['EXEC_TIME'])
            )
            ->execute()->fetchColumn();
        return $value !== false ? (string)$value : null;
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
                $queryBuilder->expr()->eq('path', $queryBuilder->quote($path))
            )
            ->execute()->fetchColumn();
        return $value !== false ? (string)$value : null;
    }

    /**
     * @return CharsetConverter
     */
    protected function getCharsetConverter():CharsetConverter
    {
        return GeneralUtility::makeInstance(CharsetConverter::class);
    }

    /**
     * @return QueryBuilder
     */
    protected function getMapQueryBuilder():QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable('tx_autourls_map');
    }

    /**
     * @return PageRepository
     */
    protected function getPageRepository():PageRepository
    {
        return $this->getTyposcriptFrontendController()->sys_page;
    }

    /**
     * @return TemplateService
     */
    protected function getTemplateService():TemplateService
    {
        return $this->getTyposcriptFrontendController()->tmpl;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTyposcriptFrontendController():TypoScriptFrontendController
    {
        return $GLOBALS['TSFE'];
    }

    /**
     * @param string $queryString
     * @param string $path
     */
    protected function insertOrRenewMapEntry(string $queryString, string $path)
    {
        $queryBuilder = $this->getMapQueryBuilder();
        $recordExists = (bool)$queryBuilder
            ->select('querystring_hash')
            ->from('tx_autourls_map')
            ->where(
                $queryBuilder->expr()->eq('querystring_hash', $this->hashQueryString($queryString))
            )
            ->execute()->fetchColumn();
        if ($recordExists) {
            $queryBuilder
                ->update('tx_autourls_map')
                ->where($queryBuilder->expr()->eq('querystring_hash', $this->hashQueryString($queryString)))
                ->set('encoding_expires', $GLOBALS['EXEC_TIME'] + 3600)
                ->execute();
        } else {
            $queryBuilder
                ->insert('tx_autourls_map')
                ->values([
                    'querystring_hash' => $this->hashQueryString($queryString),
                    'querystring' => $queryString,
                    'path' => $path,
                    'encoding_expires' => $GLOBALS['EXEC_TIME'] + 3600,
                ])
                ->execute();
        }
    }

}
