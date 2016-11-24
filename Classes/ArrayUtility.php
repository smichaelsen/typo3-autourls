<?php
declare(strict_types=1);
namespace Smichaelsen\Autourls;

class ArrayUtility
{

    /**
     * @param array $haystackArray
     * @param array $needleArray
     * @return bool
     */
    public static function array_has_all_keys_of_array(array $haystackArray, array $needleArray):bool
    {
        if (!self::array_all_keys_exist($haystackArray, array_keys($needleArray))) {
            return false;
        }
        foreach ($needleArray as $needleKey => $needleValue) {
            if (
                is_array($needleValue)
                && !self::array_has_all_keys_of_array($haystackArray[$needleKey], $needleArray[$needleKey])) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array $array
     * @param array $keys
     * @return bool
     */
    protected static function array_all_keys_exist(array $array, array $keys):bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }

}
