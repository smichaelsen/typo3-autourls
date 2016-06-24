CREATE TABLE tx_autourls_map (
	querystring_hash int(11) unsigned NOT NULL default '0',
	querystring text,
	encoding_expires int(11) unsigned NOT NULL default '0',
	path text,
	path_hash int(11) unsigned NOT NULL default '0',

	PRIMARY KEY (querystring_hash),
	UNIQUE KEY path_hash (path_hash)
);
