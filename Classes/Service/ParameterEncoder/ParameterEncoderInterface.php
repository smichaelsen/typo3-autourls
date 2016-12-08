<?php
namespace Smichaelsen\Autourls\Service\ParameterEncoder;

use TYPO3\CMS\Core\SingletonInterface;

interface ParameterEncoderInterface extends SingletonInterface
{

    /**
     * @param array $urlEncodingData
     * @return array
     */
    public function encode(array $urlEncodingData);
}
