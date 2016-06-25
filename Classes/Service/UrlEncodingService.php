<?php
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\TimeTracker\TimeTracker;
use TYPO3\CMS\Core\TypoScript\TemplateService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

class UrlEncodingService extends AbstractUrlMapService implements SingletonInterface
{

    /**
     * @param string $queryString
     * @return string
     */
    public function encodeFromQueryString(string $queryString):string
    {
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
        return $prefix . $path;
    }

    /**
     * @param array $parametersArray
     * @return string
     */
    public function encodeFromParametersArray(array $parametersArray):string
    {
        return $this->encodeFromQueryString($this->parametersArrayToQueryString($parametersArray));
    }

    /**
     * @param string $queryString
     */
    public function invalidateEncodingCacheFromQueryString(string $queryString)
    {
        $queryBuilder = $this->getMapQueryBuilder();
        $queryBuilder
            ->update('tx_autourls_map')
            ->where($queryBuilder->expr()->eq('querystring_hash', $this->fastHash($queryString)))
            ->set('encoding_expires', 0)
            ->execute();
    }

    /**
     * @param array $parametersArray
     */
    public function invalidateEncodingCacheFromParametersArray(array $parametersArray)
    {
        $this->invalidateEncodingCacheFromQueryString($this->parametersArrayToQueryString($parametersArray));
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
                $queryBuilder->expr()->eq('querystring_hash', $this->fastHash($queryString)),
                $queryBuilder->expr()->gt('encoding_expires', $GLOBALS['EXEC_TIME'])
            )
            ->orderBy('encoding_expires', 'DESC')
            ->setMaxResults(1)
            ->execute()->fetchColumn();
        return $value !== false ? (string)$value : null;
    }

    /**
     * @param int $id
     * @return string
     */
    protected function getPathForPageId(int $id):string
    {
        $pathSegments = [];
        $rootline = $this->getRootline($id);
        foreach ($rootline as $rootlinePage) {
            if ($rootlinePage['is_siteroot']) {
                break;
            }
            $slugField = '';
            foreach (['nav_title', 'title', 'uid'] as $possibleSlugField) {
                if (!empty($rootlinePage[$possibleSlugField])) {
                    $slugField = $possibleSlugField;
                    break;
                }
            }
            $pathSegments[] = $this->slugify($rootlinePage[$slugField]);
        }
        return join('/', array_reverse($pathSegments));
    }

    /**
     * Get the rootline directly from RootlineUtility instead of TSFE->sys_page to circumvent the rootline cache
     *
     * @param int $id
     * @return array
     */
    protected function getRootline(int $id):array
    {
        $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $id, '', $this->getPageRepository());
        $rootline->purgeCaches();
        GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_rootline')->flush();
        return $rootline->get();
    }

    /**
     * @param string $queryString
     * @param string $path
     */
    protected function insertOrRenewMapEntry(string $queryString, string $path)
    {
        $combinedHash = $this->fastHash($queryString . ':' . $path);
        $queryBuilder = $this->getMapQueryBuilder();
        $recordExists = (bool)$queryBuilder
            ->select('querystring_hash')
            ->from('tx_autourls_map')
            ->where(
                $queryBuilder->expr()->eq('combined_hash', $combinedHash)
            )
            ->execute()->fetchColumn();
        if ($recordExists) {
            $queryBuilder
                ->update('tx_autourls_map')
                ->where($queryBuilder->expr()->eq('combined_hash', $combinedHash))
                ->set('encoding_expires', $this->getExpiryTimestamp())
                ->set('path', $path)
                ->set('path_hash', $this->fastHash($path))
                ->execute();
        } else {
            $queryBuilder
                ->insert('tx_autourls_map')
                ->values([
                    'combined_hash' => $combinedHash,
                    'querystring_hash' => $this->fastHash($queryString),
                    'querystring' => $queryString,
                    'path' => $path,
                    'path_hash' => $this->fastHash($path),
                    'encoding_expires' => $this->getExpiryTimestamp(),
                ])
                ->execute();
        }
    }

    /**
     * Expiry is about a day but randomly distributed to avoid expiry of many paths at once
     *
     * @return int
     */
    protected function getExpiryTimestamp():int
    {
        $lifetime = 86400;
        $variance = .25;
        $lifetime = rand((1 - $variance) * $lifetime, (1 + $variance) * $lifetime);
        return $GLOBALS['EXEC_TIME'] + $lifetime;
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
     * @return CharsetConverter
     */
    protected function getCharsetConverter():CharsetConverter
    {
        return GeneralUtility::makeInstance(CharsetConverter::class);
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
        static $typoscriptFrontendController;
        if (!$typoscriptFrontendController instanceof TypoScriptFrontendController) {
            $typoscriptFrontendController = $GLOBALS['TSFE'] ?? $this->createTsfeInstance();
        }
        return $typoscriptFrontendController;
    }

    /**
     * @param int $id
     * @param int $typeNum
     * @return TypoScriptFrontendController
     */
    protected function createTsfeInstance($id = 1, $typeNum = 0):TypoScriptFrontendController
    {
        if (!is_object($GLOBALS['TT'])) {
            $GLOBALS['TT'] = new TimeTracker();
            $GLOBALS['TT']->start();
        }
        $typoscriptFrontendController = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], $id, $typeNum);
        $typoscriptFrontendController->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        $typoscriptFrontendController->sys_page->init(TRUE);
        $typoscriptFrontendController->connectToDB();
        $typoscriptFrontendController->initFEuser();
        $typoscriptFrontendController->determineId();
        $typoscriptFrontendController->initTemplate();
        $typoscriptFrontendController->rootLine = $typoscriptFrontendController->sys_page->getRootLine($id, '');
        $typoscriptFrontendController->getConfigArray();
        return $typoscriptFrontendController;
    }

}
