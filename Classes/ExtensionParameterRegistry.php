<?php
namespace Smichaelsen\Autourls;

/**
 *
 */
class ExtensionParameterRegistry
{

    /**
     * @var array
     */
    protected static $registry = [];

    /**
     * This method is used to create support for extension url parameters.
     * Take a look at ext_localconf.php for example usage
     *
     * @param string $extensionName The extension you want to support
     * @param string $queryString The queryString you want to replace. It can contain up to one value "_UID_",
     * which indicates the value represents a uid of a database record.
     * @param string $tableName If a _UID_ is present this table is used to generate a speaking path segment for the record.
     * The TCA label config for table is used as a basis for the url slug.
     */
    public static function register(string $extensionName, string $queryString, string $tableName)
    {
        self::$registry[$extensionName] = [
            'queryString' => $queryString,
            'tableName' => $tableName,
        ];
    }

    /**
     * @return array
     */
    public static function get():array
    {
        return self::$registry;
    }

}
