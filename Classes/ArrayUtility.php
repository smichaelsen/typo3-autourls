<?php
namespace Smichaelsen\Autourls;

class ArrayUtility
{

    /**
     * @param array $array
     * @param array $keys
     * @return bool
     */
    public static function array_all_keys_exist(array $array, array $keys):bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }

}
