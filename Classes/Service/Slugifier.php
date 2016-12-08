<?php
namespace Smichaelsen\Autourls\Service;

use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\SingletonInterface;

class Slugifier implements SingletonInterface
{

    /**
     * @var CharsetConverter
     */
    protected $charsetConverter;

    /**
     * @param CharsetConverter $charsetConverter
     */
    public function injectCharsetConverter(CharsetConverter $charsetConverter)
    {
        $this->charsetConverter = $charsetConverter;
    }

    /**
     * @param string $title
     * @return string
     */
    public function slugify($title)
    {
        $charset = empty($GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset']) ? 'utf-8' : $GLOBALS['TYPO3_CONF_VARS']['BE']['forceCharset'];
        $slug = $this->charsetConverter->conv_case($charset, $title, 'toLower');
        $slug = strip_tags($slug);
        $slug = str_replace(['%', '?', '!', '#'], '', $slug);
        $slug = preg_replace('/[ \\\\\\/\'&\-+_]+/', '-', $slug);
        $slug = trim($slug, '-');
        $slug = $this->charsetConverter->specCharsToASCII($charset, $slug);
        return $slug;
    }
}
