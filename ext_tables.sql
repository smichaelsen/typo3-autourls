CREATE TABLE tx_autourls_map (
	combined_hash int(11) unsigned NOT NULL default '0',
	querystring_hash int(11) unsigned NOT NULL default '0',
	querystring text,
	encoding_expires int(11) unsigned NOT NULL default '0',
	path text,
	path_hash int(11) unsigned NOT NULL default '0',
	is_shortcut tinyint(4) unsigned NOT NULL default '0',

	PRIMARY KEY (combined_hash),
	KEY querystring_hash (querystring_hash),
	KEY path_hash (path_hash)
);
