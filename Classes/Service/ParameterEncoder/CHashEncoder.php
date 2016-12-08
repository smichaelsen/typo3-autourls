<?php
namespace Smichaelsen\Autourls\Service\ParameterEncoder;

class CHashEncoder implements ParameterEncoderInterface
{

    /**
     * @param array $urlEncodingData
     * @return array
     */
    public function encode(array $urlEncodingData)
    {
        if (count($urlEncodingData['remainingUrlParameters']) === 1 && isset($urlEncodingData['remainingUrlParameters']['cHash'])) {
            $urlEncodingData['cHash'] = $urlEncodingData['remainingUrlParameters']['cHash'];
            unset($urlEncodingData['remainingUrlParameters']['cHash']);
        }
        return $urlEncodingData;
    }
}
