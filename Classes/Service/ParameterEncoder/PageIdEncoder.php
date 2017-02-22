<?php
namespace Smichaelsen\Autourls\Service\ParameterEncoder;

use Smichaelsen\Autourls\Service\Slugifier;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\TimeTracker\NullTimeTracker;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\RootlineUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\CMS\Frontend\Page\PageRepository;

class PageIdEncoder implements ParameterEncoderInterface
{

    const SUPPORTED_DOKTYPES = [
        PageRepository::DOKTYPE_DEFAULT,
        PageRepository::DOKTYPE_SHORTCUT,
    ];

    /**
     * @var PageRepository
     */
    protected $pageRepository;

    /**
     * @param PageRepository $pageRepository
     */
    public function injectPageRepository(PageRepository $pageRepository)
    {
        $this->pageRepository = $pageRepository;
    }

    /**
     * @var Slugifier
     */
    protected $slugifier;

    /**
     * @param Slugifier $slugifier
     */
    public function injectSlugifier(Slugifier $slugifier)
    {
        $this->slugifier = $slugifier;
    }

    /**
     * @var TypoScriptFrontendController
     */
    protected $typoscriptFrontendController;

    /**
     *
     */
    public function initializeObject()
    {
        if (!$GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            $this->initializeGlobalsTsfe();
        }
        $this->typoscriptFrontendController = $GLOBALS['TSFE'];
    }

    /**
     * @param array $urlEncodingData
     * @return array
     * @throws \Exception
     */
    public function encode(array $urlEncodingData)
    {
        if (isset($urlEncodingData['remainingUrlParameters']['id'])) {
            $pageRecord = BackendUtility::getRecord('pages', (int) $urlEncodingData['remainingUrlParameters']['id']);
            if (!in_array((int) $pageRecord['doktype'], self::SUPPORTED_DOKTYPES)) {
                return $urlEncodingData;
            }
            if ($urlEncodingData['targetLanguageUid'] > 0) {
                $pageRecord = $this->pageRepository->getPageOverlay($pageRecord, $urlEncodingData['targetLanguageUid']);
            }
            if (
                (int) $pageRecord['doktype'] === PageRepository::DOKTYPE_SHORTCUT
                && (int) $pageRecord['shortcut_mode'] === PageRepository::SHORTCUT_MODE_NONE
            ) {
                $pageRecord = $this->typoscriptFrontendController->getPageShortcut(
                    $pageRecord['shortcut'],
                    $pageRecord['shortcut_mode'],
                    $pageRecord['uid']
                );
                $urlEncodingData['isShortcut'] = true;
            }
            $rootline = $this->getRootline($pageRecord['uid'], $urlEncodingData['targetLanguageUid']);
            $urlEncodingData['rootPage'] = $rootline[0]['uid'];
            $pagePathSegment = $this->getPathForPageRecord($rootline);
            if (!empty($pagePathSegment)) {
                $urlEncodingData['encodedPathSegments'][] = $pagePathSegment;
            }
            unset($urlEncodingData['remainingUrlParameters']['id']);
        }
        return $urlEncodingData;
    }

    /**
     * @param array $rootline
     * @return string
     */
    protected function getPathForPageRecord(array $rootline)
    {
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
            $pathSegments[] = $this->slugifier->slugify($rootlinePage[$slugField]);
        }
        return join('/', array_reverse($pathSegments));
    }

    /**
     * Get the rootline directly from RootlineUtility instead of TSFE->sys_page to circumvent the rootline cache
     *
     * @param int $pageId
     * @param int $targetLanguageUid
     * @return array
     */
    protected function getRootline($pageId, $targetLanguageUid = 0)
    {
        static $rootlines = [];
        $key = $pageId . ':' . $targetLanguageUid;
        if (!isset($rootlines[$key])) {
            $rootline = GeneralUtility::makeInstance(RootlineUtility::class, $pageId, '', $this->pageRepository);
            $rootline->purgeCaches();
            GeneralUtility::makeInstance(CacheManager::class)->getCache('cache_rootline')->flush();
            $rootlineArray = $rootline->get();
            if ($targetLanguageUid > 0) {
                $rootlineArray = $this->pageRepository->getPagesOverlay($rootlineArray, $targetLanguageUid);
            }
            $rootlines[$key] = $rootlineArray;
        }
        return $rootlines[$key];
    }

    /**
     * @param int $id
     * @param int $typeNum
     */
    protected function initializeGlobalsTsfe($id = 1, $typeNum = 0)
    {
        if ($GLOBALS['TSFE'] instanceof TypoScriptFrontendController) {
            return;
        }
        $GLOBALS['TT'] = new NullTimeTracker();
        $GLOBALS['TSFE'] = GeneralUtility::makeInstance(TypoScriptFrontendController::class, $GLOBALS['TYPO3_CONF_VARS'], $id, $typeNum);
        $GLOBALS['TSFE']->sys_page = GeneralUtility::makeInstance(PageRepository::class);
        $GLOBALS['TSFE']->sys_page->init(TRUE);
        $GLOBALS['TSFE']->connectToDB();
        $GLOBALS['TSFE']->initFEuser();
        $GLOBALS['TSFE']->determineId();
        $GLOBALS['TSFE']->initTemplate();
        $GLOBALS['TSFE']->rootLine = $GLOBALS['TSFE']->sys_page->getRootLine($id, '');
        $GLOBALS['TSFE']->getConfigArray();
    }

}
