# autourls

## Zero configuration speaking URLs for TYPO3 8

After installing `autourls` your TYPO3 website will have speaking URLs without any configuration.

## Installation

`composer require smichaelsen/autourls`

Afterwards just install it in the Extension Manager as usual.

### Cool! Does it replace realurl?

No. `autourls` offers only very limited functionality and is no match to `realurl` in terms of features and flexibility.

### Okay, then what does it do?

It just creates a speaking path for each page based on its rootline. So the rootline "Service > About us" results in the path `/service/about-us`. It respects the `nav_title` field of `pages` and the `config.absRefPrefix` TypoScript setting.
After renaming a page the "old" url will still be accessible.

### What about extension parameters?

Not supported yet, but will follow pretty soon.

### What about multi language handling?

Not supported yet, but will follow pretty soon.

### What about mountpoints, shortcuts, workspaces, ...?

Not supported yet. Shortcuts should follow pretty soon. Everything else might follow as there is demand for it.

### How can I configure ...?

You can't. Until now autourls is literally zero configuration.

### But that doesn't fit my requirements!

Okay, just go with `realurl` then :)
