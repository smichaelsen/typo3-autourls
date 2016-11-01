<?php
namespace Smichaelsen\Autourls\Hook;

use Smichaelsen\Autourls\Service\UrlEncodingService;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DataHandlerHook
{

    /**
     * @param string $status
     * @param string $table
     * @param string $id
     * @param array $fieldArray
     * @param DataHandler $dataHandler
     */
    public function processDatamap_afterDatabaseOperations($status, $table, $id, array $fieldArray, DataHandler $dataHandler)
    {
        if (!$this->tableIsSupported($table)) {
            return;
        }
        if (!is_numeric($id)) {
            $id = (int)$dataHandler->substNEWwithIDs[$id];
        }
        if ($status === 'update') {
            $this->invalidateEncodingCache($table, $id);
        }
        $this->warmUpEncodingCache($table, $id);
    }

    /**
     * @param string $table
     * @param int $id
     */
    protected function invalidateEncodingCache($table, $id)
    {
        if ($table === 'pages') {
            $this->getUrlEncodingService()->invalidateEncodingCacheFromParametersArray(['id' => $id]);
        }
    }

    /**
     * @param string $table
     * @param int $id
     */
    protected function warmUpEncodingCache($table, $id)
    {
        // trigger encoding to warm up the cache
        if ($table === 'pages') {
            $this->getUrlEncodingService()->encodeFromParametersArray(['id' => $id]);
        }
    }

    /**
     * @param string $tableName
     * @return bool
     */
    protected function tableIsSupported($tableName)
    {
        return $tableName === 'pages';
    }

    /**
     * @return UrlEncodingService
     */
    protected function getUrlEncodingService()
    {
        return GeneralUtility::makeInstance(UrlEncodingService::class);
    }

}
