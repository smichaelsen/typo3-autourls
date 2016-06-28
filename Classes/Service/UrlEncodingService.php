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
    ];

    /**
     * @param string $queryString
     * @return string
     */
    public function encodeFromQueryString(string $queryString):string
    {
        $isShortcut = false;
        $path = $this->findPathForQueryStringInMap($queryString);
        if ($path === null) {
            $urlParameters = $this->queryStringToParametersArray($queryString);
            $pathSegments = [];
            if (isset($urlParameters['id'])) {
                $pageRecord = BackendUtility::getRecord('pages', (int)$urlParameters['id']);
                if ((int)$pageRecord['doktype'] === PageRepository::DOKTYPE_SHORTCUT) {
                    $pageRecord = $this->getTyposcriptFrontendController()->getPageShortcut(
                        $pageRecord['shortcut'],
                        $pageRecord['shortcut_mode'],
                        $pageRecord['uid']
                    );
                    $isShortcut = true;
                }
                $pagePathSegment = $this->getPathForPageRecord($pageRecord);
                if ($pagePathSegment === null) {
                    return $queryString;
                }
                if (!empty($pagePathSegment)) {
                    $pathSegments[] = $pagePathSegment;
                }
                unset($urlParameters['id']);
            }
            foreach (ExtensionParameterRegistry::get() as $extensionName => $extensionConfiguration) {
                $extensionQueryParameters = $this->queryStringToParametersArray($extensionConfiguration['queryString']);
                if (ArrayUtility::array_has_all_keys_of_array($urlParameters, $extensionQueryParameters)) {
                    $pathSegments[] = $this->slugify($extensionName);
                    $this->replaceExtensionParameters(
                        $urlParameters,
                        $pathSegments,
                        $this->queryStringToParametersArray($extensionConfiguration['queryString']),
                        $extensionConfiguration['tableName'] ?? null
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
     * @param array $targetPage
     * @return string|null
     */
    protected function getPathForPageRecord(array $targetPage)
    {
        if ((int)$targetPage['doktype'] !== PageRepository::DOKTYPE_DEFAULT) {
            return null;
        }
        $rootline = $this->getRootline($targetPage['uid']);
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
     * @param bool $isShortcut
     */
    protected function insertOrRenewMapEntry(string $queryString, string $path, bool $isShortcut)
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
                ->set('is_shortcut', $isShortcut)
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
                    'is_shortcut' => $isShortcut,
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
     * @param array $urlParameters
     * @param array $pathSegments
     * @param array $extensionParameters
     * @param string $extensionTableName
     */
    protected function replaceExtensionParameters(array &$urlParameters, array &$pathSegments, array $extensionParameters, string $extensionTableName = null)
    {
        foreach ($extensionParameters as $extensionParameterName => $extensionParameterValue) {
            if ($extensionParameterValue === $urlParameters[$extensionParameterName]) {
                unset($urlParameters[$extensionParameterName]);
            } elseif ($extensionParameterValue === '_PASS_') {
                $pathSegments[] = $this->slugify($urlParameters[$extensionParameterName]);
                unset($urlParameters[$extensionParameterName]);
            } elseif ($extensionParameterValue === '_UID_') {
                if ($extensionTableName === null) {
                    throw new \Exception('');
                }
                $pathSegments[] = $this->slugify(
                    BackendUtility::getRecordTitle(
                        $extensionTableName,
                        BackendUtility::getRecord($extensionTableName, $urlParameters[$extensionParameterName])
                    )
                );
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
