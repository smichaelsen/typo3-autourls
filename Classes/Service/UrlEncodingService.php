<?php
namespace Smichaelsen\Autourls\Service;

use Smichaelsen\Autourls\ArrayUtility;
use Smichaelsen\Autourls\ExtensionParameterRegistry;
use TYPO3\CMS\Backend\Utility\BackendUtility;
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

    const SUPPORTED_DOKTYPES = [
        PageRepository::DOKTYPE_DEFAULT,
        PageRepository::DOKTYPE_SHORTCUT,
    ];

    /**
     * @param string $queryString
     * @return string
     */
    public function encodeFromQueryString($queryString)
    {
        $isShortcut = false;
        $targetLanguageUid = 0;
        $path = $this->findPathForQueryStringInMap($queryString);
        if ($path === null) {
            $urlParameters = $this->queryStringToParametersArray($queryString);
            $pathSegments = [];
            if (isset($urlParameters['L'])) {
                if ((int) $urlParameters['L'] > 0) {
                    $languageRecord = BackendUtility::getRecord('sys_language', (int) $urlParameters['L']);
                    if (is_array($languageRecord)) {
                        $targetLanguageUid = (int) $urlParameters['L'];
                        $pathSegments[] = $languageRecord['language_isocode'];
                    }
                }
                unset($urlParameters['L']);
            }
            if (isset($urlParameters['id'])) {
                $pageRecord = BackendUtility::getRecord('pages', (int) $urlParameters['id']);
                if ($targetLanguageUid > 0) {
                    $pageRecord = $this->getPageRepository()->getPageOverlay($pageRecord, $targetLanguageUid);
                }
                if (
                    (int) $pageRecord['doktype'] === PageRepository::DOKTYPE_SHORTCUT
                    && (int) $pageRecord['shortcut_mode'] === PageRepository::SHORTCUT_MODE_NONE
                ) {
                    $pageRecord = $this->getTyposcriptFrontendController()->getPageShortcut(
                        $pageRecord['shortcut'],
                        $pageRecord['shortcut_mode'],
                        $pageRecord['uid']
                    );
                    $isShortcut = true;
                }
                $pagePathSegment = $this->getPathForPageRecord($pageRecord, $targetLanguageUid);
                if ($pagePathSegment === null) {
                    return '?' . $queryString;
                }
                if (!empty($pagePathSegment)) {
                    $pathSegments[] = $pagePathSegment;
                }
                unset($urlParameters['id']);
            }
            foreach (ExtensionParameterRegistry::get() as $routeName => $routeConfiguration) {
                $extensionQueryParameters = $this->queryStringToParametersArray($routeConfiguration['queryString']);
                if (ArrayUtility::array_has_all_keys_of_array($urlParameters, $extensionQueryParameters)) {
                    $this->replaceExtensionParameters(
                        $urlParameters,
                        $pathSegments,
                        $this->queryStringToParametersArray($routeConfiguration['queryString']),
                        empty($routeConfiguration['tableName']) ? null : $routeConfiguration['tableName'],
                        $targetLanguageUid
                    );
                }
            }
            if (count($urlParameters) === 1 && isset($urlParameters['cHash'])) {
                unset($urlParameters['cHash']);
            }
            $path = join('/', $pathSegments);
            if (count($urlParameters)) {
                $path .= '?' . $this->parametersArrayToQueryString($urlParameters);
            }
            $this->insertOrRenewMapEntry($queryString, $path, $isShortcut);
        }
        $prefix = $this->getTemplateService()->setup['config.']['absRefPrefix'];
        if (empty($prefix)) {
            $prefix = '/';
        }
        return rtrim($prefix . $path, '/') . '/';
    }

    /**
     * @param array $parametersArray
     * @return string
     */
    public function encodeFromParametersArray(array $parametersArray)
    {
        return $this->encodeFromQueryString($this->parametersArrayToQueryString($parametersArray));
    }

    /**
     * @param string $queryString
     */
    public function invalidateEncodingCacheFromQueryString($queryString)
    {
        $this->getDatabaseConnection()->exec_UPDATEquery(
            'tx_autourls_map',
            'querystring = ' . $this->getDatabaseConnection()->fullQuoteStr($queryString, 'tx_autourls_map'),
            ['encoding_expires' => 0]
        );
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
    protected function findPathForQueryStringInMap($queryString)
    {
        $record = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            'querystring',
            'tx_autourls_map',
            'querystring = ' . $this->getDatabaseConnection()->fullQuoteStr($queryString, 'tx_autourls_map') . ' AND encoding_expires > ' . (int) $GLOBALS['EXEC_TIME'],
            '',
            'encoding_expires DESC'
        );
        if (is_array($record) && !empty($record['path'])) {
            return $record['path'];
        }
        return null;
    }

    /**
     * @param array $targetPage
     * @param int $targetLanguageUid
     * @return null|string
     */
    protected function getPathForPageRecord(array $targetPage, $targetLanguageUid)
    {
        if (!in_array((int) $targetPage['doktype'], self::SUPPORTED_DOKTYPES)) {
            return null;
        }
        $rootline = $this->getRootline($targetPage['uid']);
        if ($targetLanguageUid > 0) {
            $rootline = $this->getPageRepository()->getPagesOverlay($rootline, $targetLanguageUid);
        }
        $pathSegments = [];
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
    protected function getRootline($id)
    {
        static $rootlines = [];
        if (!isset($rootlines[$id])) {
            $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $id, '', $this->getPageRepository());
            $rootline->purgeCaches();
            GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_rootline')->flush();
            $rootlines[$id] = $rootline->get();
        }
        return $rootlines[$id];
    }

    /**
     * @param string $queryString
     * @param string $path
     * @param bool $isShortcut
     */
    protected function insertOrRenewMapEntry($queryString, $path, $isShortcut)
    {
        $urlParameters = $this->queryStringToParametersArray($queryString);
        $rootPageUid = $this->getRootline($urlParameters['id'])[0]['uid'];
        $query = sprintf(
            '
            INSERT INTO
                tx_autourls_map (encoding_expires, is_shortcut, path, querystring, rootpage_id)
            VALUES
                (%1$d, %2$d, %3$s, %4$s, %5$d)
            ON DUPLICATE KEY UPDATE
                encoding_expires = %1$d
            ',
            $this->getExpiryTimestamp(),
            $isShortcut,
            $this->getDatabaseConnection()->fullQuoteStr($path, 'tx_autourls_map'),
            $this->getDatabaseConnection()->fullQuoteStr($queryString, 'tx_autourls_map'),
            $rootPageUid
        );
        $this->getDatabaseConnection()->sql_query($query);
    }

    /**
     * Expiry is about a day but randomly distributed to avoid expiry of many paths at once
     *
     * @return int
     */
    protected function getExpiryTimestamp()
    {
        if ($GLOBALS['TSFE']->no_cache || (boolean) $GLOBALS['TSFE']->page['no_cache']) {
            $lifetime = 0;
        } else {
            $lifetime = 86400;
            $variance = .25;
            $lifetime = rand((1 - $variance) * $lifetime, (1 + $variance) * $lifetime);
        }
        return $GLOBALS['EXEC_TIME'] + $lifetime;
    }

    /**
     * @param string $title
     * @return string
     */
    protected function slugify($title)
    {
        $charset = empty($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']) ? 'utf-8' : $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
        $slug = $this->getCharsetConverter()->conv_case($charset, $title, 'toLower');
        $slug = strip_tags($slug);
        $slug = str_replace('%', '', $slug);
        $slug = preg_replace('/[ \-+_]+/', '-', $slug);
        $slug = $this->getCharsetConverter()->specCharsToASCII($charset, $slug);
        return $slug;
    }

    /**
     * @param array $urlParameters
     * @param array $pathSegments
     * @param array $extensionParameters
     * @param string $extensionTableName
     * @param int $targetLanguageUid
     * @throws \Exception
     */
    protected function replaceExtensionParameters(array &$urlParameters, array &$pathSegments, array $extensionParameters, $extensionTableName = null, $targetLanguageUid = 0)
    {
        foreach ($extensionParameters as $extensionParameterName => $extensionParameterValue) {
            if ($extensionParameterValue === $urlParameters[$extensionParameterName]) {
                unset($urlParameters[$extensionParameterName]);
            } elseif ($extensionParameterValue === '_PASS_') {
                $pathSegments[] = $this->slugify($urlParameters[$extensionParameterName]);
                unset($urlParameters[$extensionParameterName]);
            } elseif ($extensionParameterValue === '_UID_') {
                if ($extensionTableName === null) {
                    throw new \Exception('There is an autourl extension parameter query string with _UID_ parameter but without defined table name', 1467096979);
                }
                $record = BackendUtility::getRecord($extensionTableName, $urlParameters[$extensionParameterName]);
                if (is_array($record)) {
                    if ($targetLanguageUid > 0) {
                        $record = $this->getPageRepository()->getRecordOverlay($extensionTableName, $record, $targetLanguageUid);
                    }
                    $value = BackendUtility::getRecordTitle($extensionTableName, $record);
                } else {
                    $value = $urlParameters[$extensionParameterName];
                }
                $pathSegments[] = $this->slugify($value);
                unset($urlParameters[$extensionParameterName]);
            } elseif (is_array($extensionParameterValue)) {
                $this->replaceExtensionParameters(
                    $urlParameters[$extensionParameterName],
                    $pathSegments,
                    $extensionParameters[$extensionParameterName],
                    $extensionTableName
                );
                if (count($urlParameters[$extensionParameterName]) === 0) {
                    unset($urlParameters[$extensionParameterName]);
                }
            }
        }
    }

    /**
     * @return CharsetConverter
     */
    protected function getCharsetConverter()
    {
        return GeneralUtility::makeInstance(CharsetConverter::class);
    }

    /**
     * @return PageRepository
     */
    protected function getPageRepository()
    {
        return $this->getTyposcriptFrontendController()->sys_page;
    }

    /**
     * @return TemplateService
     */
    protected function getTemplateService()
    {
        return $this->getTyposcriptFrontendController()->tmpl;
    }

    /**
     * @return TypoScriptFrontendController
     */
    protected function getTyposcriptFrontendController()
    {
        static $typoscriptFrontendController;
        if (!$typoscriptFrontendController instanceof TypoScriptFrontendController) {
            $typoscriptFrontendController = empty($GLOBALS['TSFE']) ? $this->createTsfeInstance() : $GLOBALS['TSFE'];
        }
        return $typoscriptFrontendController;
    }

    /**
     * @param int $id
     * @param int $typeNum
     * @return TypoScriptFrontendController
     */
    protected function createTsfeInstance($id = 1, $typeNum = 0)
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
