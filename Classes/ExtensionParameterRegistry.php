<?php
namespace Smichaelsen\Autourls;

/**
 * How to replace an extensions query string with a nice URL:
 *
 * * Copy the part of the URL you want to rewrite, e.g. tx_news_pi1[news]=42&tx_news_pi1[controller]=News&tx_news_pi1[action]=detail
 * * Always leave out the cHash parameter. Don't try to replace it. It will be replaced automatically when all other parameters are rewritten.
 * * Put a call to \Smichaelsen\Autourls\ExtensionParameterRegistry::register() into your ext_localconf.php
 * * The 1st method argument is the route name. It will be the start of the created url segment. Let's choose "news" here.
 * * The 2nd method argument is the query string you want to replace. Pay special attention to the values:
 * * * Our example query string has 3 parameters: tx_news_pi1[news]=42, tx_news_pi1[controller]=News and tx_news_pi1[action]=detail
 * * * The first one obviously is variable. It's not always 42. It is a uid that we want to be replaced with the title of a database record. Therefore we replace it with _UID_
 * * * The second and third ones are static. Out route makes only sense if these two are set to News and detail. We keep them in the query string like that. They will disappear from the generated url path.
 * * The 3rd method argument is a table name that needs to be provided if our query string contains a _UID_. The TCA label configuration will be used to get a title for the database record.
 * * Our example result is: \Smichaelsen\Autourls\ExtensionParameterRegistry::register('News', 'tx_news_pi1[news]=_UID_&tx_news_pi1[controller]=News&tx_news_pi1[action]=detail', 'tx_news_domain_model_news');
 * * The resulting url could look like: www.example.com/path/to/news-detail/news/the-title-of-my-news
 *
 * * You can also use _PASS_ as value in your query string. If we set tx_news_pi1[news]=_PASS_ in our example we get an url like: www.example.com/path/to/news-detail/news/42
 * * While you can also use encoded query strings (tx_news_pi1%5Bnews%5D%3D42%26tx_news_pi1%5Bcontroller%5D%3DNews%26tx_news_pi1%5Baction%5D%3Ddetail) it's recommended to use decoded query strings for better readability.
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
     * Take a look at the class header comment and at ext_localconf.php for more details
     *
     * @param string $routeName
     * @param string $queryString The queryString you want to replace.
     * @param string $tableName
     */
    public static function register($routeName, $queryString, $tableName = '')
    {
        self::$registry[$routeName] = [
            'queryString' => urldecode($queryString),
            'tableName' => $tableName,
        ];
    }

    /**
     * @return array
     */
    public static function get()
    {
        return self::$registry;
    }

    /**
     *
     */
    public static function reset()
    {
        self::$registry = [];
    }
}
