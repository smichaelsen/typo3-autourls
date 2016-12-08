<?php
namespace Smichaelsen\Autourls\Service\ParameterEncoder;

use Smichaelsen\Autourls\ArrayUtility;
use Smichaelsen\Autourls\ExtensionParameterRegistry;
use Smichaelsen\Autourls\Service\Slugifier;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Frontend\Page\PageRepository;

class ExtensionParameterEncoder implements ParameterEncoderInterface
{

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
     * @param array $urlEncodingData
     * @return array
     */
    public function encode(array $urlEncodingData)
    {
        foreach (ExtensionParameterRegistry::get() as $routeName => $routeConfiguration) {
            $extensionQueryParameters = $this->queryStringToParametersArray($routeConfiguration['queryString']);
            if (ArrayUtility::array_has_all_keys_of_array($urlEncodingData['remainingUrlParameters'], $extensionQueryParameters)) {
                $this->replaceExtensionParameters(
                    $urlEncodingData['remainingUrlParameters'],
                    $urlEncodingData['encodedPathSegments'],
                    $this->queryStringToParametersArray($routeConfiguration['queryString']),
                    empty($routeConfiguration['tableName']) ? null : $routeConfiguration['tableName'],
                    $urlEncodingData['targetLanguageUid']
                );
            }
        }
        return $urlEncodingData;
    }

    /**
     * @param string $queryString
     * @return array
     */
    protected function queryStringToParametersArray($queryString)
    {
        parse_str($queryString, $urlParameters);
        return $urlParameters;
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
                $pathSegments[] = $this->slugifier->slugify($urlParameters[$extensionParameterName]);
                unset($urlParameters[$extensionParameterName]);
            } elseif ($extensionParameterValue === '_UID_') {
                if ($extensionTableName === null) {
                    throw new \Exception('There is an autourl extension parameter query string with _UID_ parameter but without defined table name', 1467096979);
                }
                $record = BackendUtility::getRecord($extensionTableName, $urlParameters[$extensionParameterName]);
                if (is_array($record)) {
                    if ($targetLanguageUid > 0) {
                        $record = $this->pageRepository->getRecordOverlay($extensionTableName, $record, $targetLanguageUid);
                    }
                    $value = BackendUtility::getRecordTitle($extensionTableName, $record);
                } else {
                    $value = $urlParameters[$extensionParameterName];
                }
                $pathSegments[] = $this->slugifier->slugify($value);
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
}
