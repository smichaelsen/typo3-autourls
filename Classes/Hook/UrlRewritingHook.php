<?php
namespace Smichaelsen\Autourls\Hook;

use Smichaelsen\Autourls\Service\UrlDecodingService;
use Smichaelsen\Autourls\Service\UrlEncodingService;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

class UrlRewritingHook
{

    /**
     * @param array $params
     */
    public function encodeUrl(array &$params)
    {
        $queryString = parse_url($params['LD']['totalURL'])['query'];
        $params['LD']['totalURL'] = GeneralUtility::makeInstance(UrlEncodingService::class)->encodeFromQueryString($queryString);
    }

    /**
     * @param array $params
     */
    public function decodeUrl(array $params)
    {
        /** @var TypoScriptFrontendController $typoscriptFrontendController */
        $typoscriptFrontendController = &$params['pObj'];
        $pagePath = $typoscriptFrontendController->siteScript;
        $decodedUrlParameters = GeneralUtility::makeInstance(UrlDecodingService::class)->decodeFromPagePath($pagePath);
        if ($decodedUrlParameters === null) {
            return;
        }
        $typoscriptFrontendController->mergingWithGetVars($decodedUrlParameters);
        $typoscriptFrontendController->id = (int)$decodedUrlParameters['id'];
    }

}
