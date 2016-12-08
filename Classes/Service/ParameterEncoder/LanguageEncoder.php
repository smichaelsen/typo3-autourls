<?php
namespace Smichaelsen\Autourls\Service\ParameterEncoder;

use TYPO3\CMS\Core\Database\DatabaseConnection;

class LanguageEncoder implements ParameterEncoderInterface
{

    /**
     * @var array
     */
    protected $languageMap;

    /**
     * @param array $urlEncodingData
     * @return array
     */
    public function encode(array $urlEncodingData)
    {
        if (isset($urlEncodingData['remainingUrlParameters']['L'])) {
            if ((int) $urlEncodingData['remainingUrlParameters']['L'] > 0) {
                $isoCode = $this->languageUidToIsoCode($urlEncodingData['remainingUrlParameters']['L']);
                if ($isoCode !== null) {
                    $urlEncodingData['targetLanguageUid'] = (int) $urlEncodingData['remainingUrlParameters']['L'];
                    $urlEncodingData['encodedPathSegments'][] = $isoCode;
                }
            }
            unset($urlEncodingData['remainingUrlParameters']['L']);
        }
        return $urlEncodingData;
    }

    /**
     * @param int $languageUid
     * @return string
     */
    protected function languageUidToIsoCode($languageUid)
    {
        if (!is_array($this->languageMap)) {
            $this->languageMap = $this->getDatabaseConnection()->exec_SELECTgetRows(
                'language_isocode',
                'sys_language',
                'uid = ' . (int) $languageUid,
                '',
                '',
                '',
                'uid'
            );
        }
        if (!isset($this->languageMap[(string) $languageUid]['language_isocode'])) {
            return null;
        }
        return $this->languageMap[(string) $languageUid]['language_isocode'];
    }

    /**
     * @return DatabaseConnection
     */
    protected function getDatabaseConnection()
    {
        return $GLOBALS['TYPO3_DB'];
    }
}
