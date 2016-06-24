CREATE TABLE tx_autourls_map (
	querystring_hash int(11) unsigned NOT NULL default '0',
	querystring text,
	encoding_expires int(11) unsigned NOT NULL default '0',
	path text,

	PRIMARY KEY (querystring_hash)
);
