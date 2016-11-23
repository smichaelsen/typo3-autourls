# autourls

## Zero configuration speaking URLs for TYPO3 8

After installing `autourls` your TYPO3 website will have speaking URLs without any configuration.

## Installation

`composer require smichaelsen/autourls`

Afterwards just install it in the Extension Manager as usual.

### Cool! Does it replace realurl?

No. `autourls` offers only limited functionality and is no match to `realurl` in terms of features and flexibility.

### Okay, then what does it do?

It just creates a speaking path for each page based on its rootline. So the rootline "Service > About us" results in the path `/service/about-us`.

* It respects the `nav_title` field of `pages`.
* It respects the `config.absRefPrefix` TypoScript setting.
* Shortcut pages are handled correctly (their path is the one from their target page, just like in realurl)

### Caching and Expiry

* For each query string the resulting speaking path will be cached in the database and will be assigned an expiry date of about one day.
* After expiration the speaking path will be created again, so that renamed record names etc will be respected.
* "Old" speaking paths (which are expired) will remain accessible.
* If `config.no_cache = 1` speaking paths will immediately expire, so they will be regenerated on every page load.
* If you need to expire a path manually, don't clear the whole database table! Go to `tx_autourls_map` and set `encoding_expires` to 0 for the desired record(s).

### What about extension parameters?

`autourls` comes with native (basic) support for the `news` extension.

Extensions can register themselves by calling `\Smichaelsen\Autourls\ExtensionParameterRegistry::register()` in their `ext_localconf.php`.
Take a look at the `ExtensionParameterRegistry` class header for more explanation.

If you want an extension to be supported by `autourls` you can contact the extension author and ask if they want to include the registration.

If they refuse, you can also [open an issue for autourls](https://github.com/smichaelsen/typo3-autourls/issues). I'm willing to add support for common open source extensions.

And of course if you have some kind of package/template extension, you can also register support there too.

### What about multi language handling?

Yes! If you use the `L` GET parameter to indicate the language (which is very common in TYPO3) it gets automatically rewritten and the page path or extension records are localized accordingly. All with zero configuration!

### What about mountpoints, workspaces, ...?

Not supported yet. Those might follow as there is demand for it.

### How can I configure ...?

You can't. Until now `autourls` is literally zero configuration.

### But that doesn't fit my requirements!

Okay, just go with `realurl` then :)
