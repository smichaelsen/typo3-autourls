<?php
defined('TYPO3_MODE') or die ('Access denied.');

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tstemplate.php']['linkData-PostProc']['Smichaelsen.Autourls'] = \Smichaelsen\Autourls\Hook\UrlRewritingHook::class . '->encodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['tslib/class.tslib_fe.php']['checkAlternativeIdMethods-PostProc']['Smichaelsen.Autourls'] = \Smichaelsen\Autourls\Hook\UrlRewritingHook::class . '->decodeUrl';
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['Smichaelsen.Autourls'] = \Smichaelsen\Autourls\Hook\DataHandlerHook::class;


if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('news')) {
    \Smichaelsen\Autourls\ExtensionParameterRegistry::register(
        'News',
        'tx_news_pi1[news]=_UID_&tx_news_pi1[controller]=News&tx_news_pi1[action]=detail',
        'tx_news_domain_model_news'
    );
}

/* When you plan to support autourls in your extension, this example is for you:

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('autourls')) {
    \Smichaelsen\Autourls\ExtensionParameterRegistry::register(
        'News',
        'tx_news_pi1[news]=_UID_&tx_news_pi1[controller]=News&tx_news_pi1[action]=detail',
        'tx_news_domain_model_news'
    );
}

*/
