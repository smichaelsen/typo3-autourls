<?php
namespace Smichaelsen\Autourls\Service;

use Smichaelsen\Autourls\Service\ParameterEncoder\CHashEncoder;
use Smichaelsen\Autourls\Service\ParameterEncoder\ExtensionParameterEncoder;
use Smichaelsen\Autourls\Service\ParameterEncoder\LanguageEncoder;
use Smichaelsen\Autourls\Service\ParameterEncoder\PageIdEncoder;
use Smichaelsen\Autourls\Service\ParameterEncoder\ParameterEncoderInterface;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;

class UrlEncodingService extends AbstractUrlMapService implements SingletonInterface
{

    /**
     * @param string $queryString
     * @return string
     */
    public function encodeFromQueryString($queryString)
    {
        $path = $this->findPathForQueryStringInMap($queryString);
        if ($path === null) {
            $urlEncodingData = [
                'originalQueryString' => $queryString,
                'remainingUrlParameters' => $this->queryStringToParametersArray($queryString),
                'encodedPathSegments' => [],
                'isShortcut' => false,
                'targetLanguageUid' => 0,
                'cHash' => null,
                'rootPage' => null,
            ];
            foreach ($this->getParameterEncoders() as $parameterEncoder) {
                $urlEncodingData = $parameterEncoder->encode($urlEncodingData);
            }
            $replacedParameters = array_diff_assoc(
                $this->queryStringToParametersArray($urlEncodingData['originalQueryString']),
                $urlEncodingData['remainingUrlParameters']
            );
            if (isset($replacedParameters['cHash'])) {
                unset($replacedParameters['cHash']);
            }
            $path = join('/', $urlEncodingData['encodedPathSegments']);
            $this->insertOrRenewMapEntry(
                $this->parametersArrayToQueryString($replacedParameters),
                $path,
                $urlEncodingData['isShortcut'],
                $urlEncodingData['cHash'],
                $urlEncodingData['rootPage']
            );
            if (!empty($path)) {
                $path = $path . '/';
            }
            if (count($urlEncodingData['remainingUrlParameters'])) {
                $path .= '?' . $this->parametersArrayToQueryString($urlEncodingData['remainingUrlParameters']);
            }
        } else {
            // add trailing slash to path from db cache
            if (!empty($path)) {
                $path .= '/';
            }
        }
        return '/' . $path;
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
     * @return array|ParameterEncoderInterface[]
     */
    protected function getParameterEncoders()
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        return [
            $objectManager->get(LanguageEncoder::class),
            $objectManager->get(PageIdEncoder::class),
            $objectManager->get(ExtensionParameterEncoder::class),
            $objectManager->get(CHashEncoder::class),
        ];
    }

    /**
     * @param string $queryString
     * @return string|null
     */
    protected function findPathForQueryStringInMap($queryString)
    {
        $record = $this->getDatabaseConnection()->exec_SELECTgetSingleRow(
            'path',
            'tx_autourls_map',
            'querystring = ' . $this->getDatabaseConnection()->fullQuoteStr($queryString, 'tx_autourls_map') . ' AND encoding_expires > ' . (int) $GLOBALS['EXEC_TIME'],
            '',
            'encoding_expires DESC'
        );
        if (is_array($record)) {
            return $record['path'];
        }
        return null;
    }

    /**
     * @param string $queryString
     * @param string $path
     * @param bool $isShortcut
     * @param string $cHash
     * @param int $rootPage
     */
    protected function insertOrRenewMapEntry($queryString, $path, $isShortcut, $cHash, $rootPage)
    {
        $query = sprintf(
            '
            INSERT INTO
                tx_autourls_map (encoding_expires, is_shortcut, path, querystring, rootpage_id, chash)
            VALUES
                (%1$d, %2$d, %3$s, %4$s, %5$d, %6$s)
            ON DUPLICATE KEY UPDATE
                encoding_expires = %1$d
            ',
            $this->getExpiryTimestamp(),
            $isShortcut,
            $this->getDatabaseConnection()->fullQuoteStr($path, 'tx_autourls_map'),
            $this->getDatabaseConnection()->fullQuoteStr($queryString, 'tx_autourls_map'),
            $rootPage,
            $this->getDatabaseConnection()->fullQuoteStr($cHash, 'tx_autourls_map')
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
}
